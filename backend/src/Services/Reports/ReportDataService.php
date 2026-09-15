<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignMetricDaily;
use App\Domain\Models\PostMetric;
use App\Domain\Models\Report;
use App\Domain\Models\Request;

/**
 * Assembles report data from the metric sources (Master Spec §15). Always
 * returns the full section structure — even when a source is thin — so the
 * report shell renders now and enriches automatically as data accrues.
 */
final class ReportDataService
{
    public function assemble(Report $report): array
    {
        return match ($report->type) {
            'campaign_wrapup'  => $this->campaignWrapup($report),
            'delivery_summary' => $this->deliverySummary($report),
            default            => $this->socialPerformance($report),
        };
    }

    /** From post_metrics (§12). */
    private function socialPerformance(Report $report): array
    {
        $rows = $this->scopeDates(PostMetric::where('client_email', $report->client_email), $report)->get();

        $totals = ['impressions' => 0, 'reach' => 0, 'engagement' => 0, 'clicks' => 0];
        $byPlatform = [];
        foreach ($rows as $m) {
            foreach ($totals as $k => $_) {
                $totals[$k] += (int) $m->{$k};
            }
            $p = (string) $m->platform;
            $byPlatform[$p] = ($byPlatform[$p] ?? 0) + (int) $m->impressions;
        }

        return [
            'section'     => 'Social performance',
            'totals'      => $totals,
            'by_platform' => $byPlatform,
            'posts_tracked' => $rows->pluck('scheduled_post_target_id')->unique()->count(),
        ];
    }

    /** From campaign_metrics_daily (§14). */
    private function campaignWrapup(Report $report): array
    {
        $campaigns = Campaign::where('client_email', $report->client_email)->pluck('id')->all();
        $rows = $this->scopeDates(
            CampaignMetricDaily::whereIn('campaign_id', $campaigns)->where('entity_type', 'campaign'),
            $report
        )->get();

        $spend = (float) $rows->sum(fn ($r) => (float) $r->spend);
        $leads = (int) $rows->sum('leads');
        $impressions = (int) $rows->sum('impressions');

        return [
            'section'    => 'Campaign wrap-up',
            'campaigns'  => count($campaigns),
            'spend'      => round($spend, 2),
            'leads'      => $leads,
            'impressions' => $impressions,
            'cpl'        => $leads > 0 ? round($spend / $leads, 2) : 0,
        ];
    }

    /** From delivered requests. */
    private function deliverySummary(Report $report): array
    {
        $q = Request::where('client_email', $report->client_email)->where('status', 'completed');
        if ($report->period_start) {
            $q->where('updated_at', '>=', $report->period_start . ' 00:00:00');
        }
        if ($report->period_end) {
            $q->where('updated_at', '<=', $report->period_end . ' 23:59:59');
        }
        $rows = $q->get();

        $byType = [];
        foreach ($rows as $r) {
            $byType[$r->type] = ($byType[$r->type] ?? 0) + 1;
        }

        return [
            'section'    => 'Delivery summary',
            'delivered'  => $rows->count(),
            'by_type'    => $byType,
        ];
    }

    private function scopeDates($query, Report $report)
    {
        if ($report->period_start) {
            $query->where('metric_date', '>=', $report->period_start);
        }
        if ($report->period_end) {
            $query->where('metric_date', '<=', $report->period_end);
        }
        return $query;
    }
}
