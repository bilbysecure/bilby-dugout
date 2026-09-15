<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Auth\Principal;
use App\Domain\Models\AdVariant;
use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignAlert;
use App\Domain\Models\CampaignMetricDaily;
use App\Domain\Models\CampaignPhase;
use App\Domain\Models\PhaseAudience;
use App\Policies\AuthorizationException;
use App\Repositories\CampaignRepository;
use App\Repositories\ProjectRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Ad Campaign Project module (Master Spec §14). Managers create/configure the
 * 3-phase campaign on an ad_campaign project; clients read their own dashboard.
 * Read/report only — audiences are RECORDED, never created via the Meta API.
 */
final class CampaignService
{
    /** Learning phase: ad sets exit after ~50 conversions/week. */
    private const LEARNING_LEADS = 50;

    public function __construct(
        private readonly CampaignRepository $repo,
        private readonly ProjectRepository $projects,
        private readonly AbComparisonService $ab,
    ) {
    }

    public function list(Principal $p): array
    {
        return $this->repo->visibleTo($p)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, array $data): Campaign
    {
        $this->assertManager($p);

        $projectId = (int) ($data['project_id'] ?? 0);
        $project = $this->projects->find($p, $projectId);
        if (!$project) {
            throw new ModelNotFoundException('Project not found');
        }
        if ($project->type !== 'ad_campaign') {
            throw new InvalidArgumentException('Campaigns can only be attached to an ad_campaign project');
        }
        if (Campaign::where('project_id', $project->id)->exists()) {
            throw new InvalidArgumentException('This project already has a campaign');
        }

        return DB::connection()->transaction(function () use ($data, $project) {
            $campaign = Campaign::create([
                'project_id'       => $project->id,
                'client_email'     => $project->client_email,
                'meta_campaign_id' => $data['meta_campaign_id'] ?? null,
                'objective'        => $data['objective'] ?? 'lead_generation',
                'total_budget'     => $data['total_budget'] ?? $project->price,
                'cpl_target'       => $data['cpl_target'] ?? null,
                'start_date'       => $data['start_date'] ?? null,
                'end_date'         => $data['end_date'] ?? null,
                'status'           => 'draft',
            ]);

            foreach ($data['phases'] ?? [] as $i => $ph) {
                $phase = CampaignPhase::create([
                    'campaign_id'    => $campaign->id,
                    'phase_number'   => (int) ($ph['phase_number'] ?? $i + 1),
                    'name'           => (string) ($ph['name'] ?? 'Phase ' . ($i + 1)),
                    'start_date'     => $ph['start_date'] ?? null,
                    'end_date'       => $ph['end_date'] ?? null,
                    'budget_split'   => $ph['budget_split'] ?? null,
                    'meta_adset_ids' => array_values((array) ($ph['meta_adset_ids'] ?? [])),
                ]);
                foreach ($ph['variants'] ?? [] as $v) {
                    AdVariant::create([
                        'phase_id'    => $phase->id,
                        'campaign_id' => $campaign->id,
                        'meta_ad_id'  => $v['meta_ad_id'] ?? null,
                        'media_id'    => isset($v['media_id']) ? (int) $v['media_id'] : null,
                        'headline'    => $v['headline'] ?? null,
                        'is_control'  => (bool) ($v['is_control'] ?? false),
                    ]);
                }
                foreach ($ph['audiences'] ?? [] as $a) {
                    PhaseAudience::create([
                        'phase_id'         => $phase->id,
                        'campaign_id'      => $campaign->id,
                        'audience_name'    => (string) ($a['audience_name'] ?? 'Audience'),
                        'meta_audience_id' => $a['meta_audience_id'] ?? null,
                        'audience_type'    => $a['audience_type'] ?? 'custom',
                        'notes'            => $a['notes'] ?? null,
                    ]);
                }
            }

            $this->createAlerts($campaign, $data['alerts'] ?? []);
            return $campaign->load('phases');
        });
    }

    /** Assemble the client-facing dashboard payload. */
    public function dashboard(Principal $p, int $id): array
    {
        $campaign = $this->repo->find($p, $id);
        if (!$campaign) {
            throw new ModelNotFoundException('Campaign not found');
        }

        $phases = $campaign->phases()->with(['variants', 'audiences'])->get();

        return [
            'campaign'        => $campaign->toArray(),
            'phases'          => $phases->map(fn (CampaignPhase $ph) => $ph->toArray() + [
                'ab' => $this->ab->compare($ph->id),
            ])->all(),
            'daily_trend'     => $this->dailyTrend($campaign->id),
            'alerts'          => CampaignAlert::where('campaign_id', $campaign->id)->get()->toArray(),
            'learning_status' => $this->learningStatus($campaign->id),
        ];
    }

    private function dailyTrend(int $campaignId): array
    {
        return CampaignMetricDaily::where('campaign_id', $campaignId)
            ->where('entity_type', 'campaign')
            ->orderBy('metric_date')
            ->get(['metric_date', 'spend', 'leads', 'cpl', 'impressions', 'clicks'])
            ->toArray();
    }

    /** Per-ad-set learning status from the last 7 days of lead volume. */
    private function learningStatus(int $campaignId): array
    {
        $since = date('Y-m-d', time() - 7 * 86400);
        $rows = CampaignMetricDaily::where('campaign_id', $campaignId)
            ->where('entity_type', 'adset')
            ->where('metric_date', '>=', $since)
            ->get();

        $byAdset = [];
        foreach ($rows as $r) {
            $byAdset[$r->entity_id] = ($byAdset[$r->entity_id] ?? 0) + (int) $r->leads;
        }
        $out = [];
        foreach ($byAdset as $adsetId => $leads) {
            $out[] = ['adset_id' => $adsetId, 'leads_7d' => $leads, 'status' => $leads >= self::LEARNING_LEADS ? 'active' : 'learning'];
        }
        return $out;
    }

    private function createAlerts(Campaign $campaign, array $alerts): void
    {
        if ($alerts === []) {
            // Sensible defaults from the playbook.
            $alerts = [
                ['rule_type' => 'cpl_over_target', 'threshold' => $campaign->cpl_target ?? 50, 'window_days' => 3],
                ['rule_type' => 'frequency_high', 'threshold' => 3],
                ['rule_type' => 'pacing_off', 'threshold' => 0.15],
            ];
        }
        foreach ($alerts as $a) {
            CampaignAlert::create([
                'campaign_id' => $campaign->id,
                'rule_type'   => (string) $a['rule_type'],
                'threshold'   => $a['threshold'] ?? null,
                'window_days' => $a['window_days'] ?? null,
                'enabled'     => $a['enabled'] ?? true,
            ]);
        }
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }
}
