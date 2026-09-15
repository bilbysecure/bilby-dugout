<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignMetricDaily;

/**
 * Upserts daily campaign insights (Master Spec §14 / §12 pattern). Keyed by
 * (entity_type, entity_id, metric_date) so Meta's within-window restatements
 * overwrite in place. Derived rates (CTR/CPL/frequency) are computed from the
 * raw counts when not supplied.
 */
final class CampaignMetricsService
{
    /**
     * @param array $metrics impressions, reach, clicks, leads, spend (+ optional ctr/cpl/frequency)
     */
    public function upsert(Campaign $campaign, string $entityType, string $entityId, string $date, array $metrics, ?array $raw = null): CampaignMetricDaily
    {
        $impressions = (int) ($metrics['impressions'] ?? 0);
        $reach = (int) ($metrics['reach'] ?? 0);
        $clicks = (int) ($metrics['clicks'] ?? 0);
        $leads = (int) ($metrics['leads'] ?? 0);
        $spend = round((float) ($metrics['spend'] ?? 0), 2);

        $ctr = $metrics['ctr'] ?? ($impressions > 0 ? round($clicks / $impressions * 100, 4) : 0);
        $cpl = $metrics['cpl'] ?? ($leads > 0 ? round($spend / $leads, 2) : 0);
        $frequency = $metrics['frequency'] ?? ($reach > 0 ? round($impressions / $reach, 4) : 0);

        $row = CampaignMetricDaily::firstOrNew([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'metric_date' => $date,
        ]);
        $row->campaign_id = $campaign->id;
        $row->client_email = $campaign->client_email;
        $row->impressions = $impressions;
        $row->reach = $reach;
        $row->clicks = $clicks;
        $row->leads = $leads;
        // Fixed strings so Eloquent's decimal cast never receives a float (brick/math).
        $row->spend = number_format((float) $spend, 2, '.', '');
        $row->ctr = number_format((float) $ctr, 4, '.', '');
        $row->cpl = number_format((float) $cpl, 2, '.', '');
        $row->frequency = number_format((float) $frequency, 4, '.', '');
        if ($raw !== null) {
            $row->raw = json_encode($raw, JSON_UNESCAPED_SLASHES);
        }
        $row->save();
        return $row;
    }

    /** Parse a Meta insights row (fields + actions[]) into our metric shape. */
    public function parseInsightRow(array $row): array
    {
        $leads = 0;
        foreach ($row['actions'] ?? [] as $action) {
            if (in_array($action['action_type'] ?? '', ['lead', 'onsite_conversion.lead_grouped', 'leadgen.other'], true)) {
                $leads += (int) ($action['value'] ?? 0);
            }
        }
        return [
            'impressions' => (int) ($row['impressions'] ?? 0),
            'reach'       => (int) ($row['reach'] ?? 0),
            'clicks'      => (int) ($row['clicks'] ?? 0),
            'spend'       => (float) ($row['spend'] ?? 0),
            'leads'       => $leads,
            'frequency'   => isset($row['frequency']) ? (float) $row['frequency'] : null,
        ] + (isset($row['ctr']) ? ['ctr' => (float) $row['ctr']] : []);
    }
}
