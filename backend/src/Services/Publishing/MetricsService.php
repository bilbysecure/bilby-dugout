<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\PostMetric;
use App\Domain\Models\ScheduledPostTarget;

/** Stores + parses post-performance metrics (Master Spec §12). */
final class MetricsService
{
    private const COLUMNS = ['impressions', 'reach', 'engagement', 'likes', 'comments', 'shares', 'saves', 'clicks'];

    /** Meta insight metric name → our column (IG + FB names). */
    private const METRIC_MAP = [
        'impressions'            => 'impressions',
        'reach'                  => 'reach',
        'engagement'             => 'engagement',
        'likes'                  => 'likes',
        'comments'               => 'comments',
        'shares'                 => 'shares',
        'saved'                  => 'saves',
        'saves'                  => 'saves',
        'clicks'                 => 'clicks',
        'post_impressions'       => 'impressions',
        'post_impressions_unique' => 'reach',
        'post_engaged_users'     => 'engagement',
        'post_clicks'            => 'clicks',
    ];

    /** Upsert a target's metrics for a date (idempotent per (target, date)). */
    public function upsert(ScheduledPostTarget $target, ?int $postId, string $clientEmail, ?string $platform, string $date, array $metrics, ?array $raw = null): PostMetric
    {
        $row = PostMetric::firstOrNew([
            'scheduled_post_target_id' => $target->id,
            'metric_date'              => $date,
        ]);
        $row->scheduled_post_id = $postId;
        $row->client_email = $clientEmail;
        $row->platform = $platform;
        foreach (self::COLUMNS as $col) {
            if (isset($metrics[$col])) {
                $row->{$col} = (int) $metrics[$col];
            }
        }
        if ($raw !== null) {
            $row->raw = json_encode($raw, JSON_UNESCAPED_SLASHES);
        }
        $row->save();
        return $row;
    }

    /** Parse a Meta insights response ({data:[{name, values:[{value}]}]}) into our columns. */
    public function parseInsights(array $response): array
    {
        $out = [];
        foreach ($response['data'] ?? [] as $metric) {
            $name = (string) ($metric['name'] ?? '');
            $value = $metric['values'][0]['value'] ?? ($metric['total_value']['value'] ?? null);
            $col = self::METRIC_MAP[$name] ?? null;
            if ($col !== null && is_numeric($value)) {
                $out[$col] = ($out[$col] ?? 0) + (int) $value;
            }
        }
        return $out;
    }

    /** Metric set to request per platform. */
    public function metricsQueryFor(string $platform): string
    {
        return $platform === 'instagram'
            ? 'impressions,reach,engagement,saved'
            : 'post_impressions,post_impressions_unique,post_engaged_users,post_clicks';
    }
}
