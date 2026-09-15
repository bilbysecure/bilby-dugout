<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Models\PostMetric;

/**
 * Content-performance views built from post_metrics (Master Spec §12), scoped
 * per role — clients see only their own tenant's numbers.
 */
final class ContentPerformanceService
{
    public function performance(Principal $p, ?string $clientEmail = null): array
    {
        $q = PostMetric::query();

        if (!$p->isAgency()) {
            $q->where('client_email', $p->clientEmail);
        } elseif ($clientEmail) {
            $q->where('client_email', $clientEmail);
        } elseif ($p->isManager() && $p->impersonatedClientEmail) {
            $q->where('client_email', $p->impersonatedClientEmail);
        }

        // Latest row per target (metrics accumulate over pulls).
        $latest = $q->orderByDesc('metric_date')->orderByDesc('id')->get()
            ->unique('scheduled_post_target_id')->values();

        $totals = ['impressions' => 0, 'reach' => 0, 'engagement' => 0, 'clicks' => 0];
        $byPost = [];
        $byPlatform = [];

        foreach ($latest as $m) {
            foreach ($totals as $k => $_) {
                $totals[$k] += (int) $m->{$k};
            }
            $pid = (int) $m->scheduled_post_id;
            $byPost[$pid] = ($byPost[$pid] ?? 0) + (int) $m->engagement;
            $plat = (string) $m->platform;
            $byPlatform[$plat] = ($byPlatform[$plat] ?? ['impressions' => 0, 'engagement' => 0]);
            $byPlatform[$plat]['impressions'] += (int) $m->impressions;
            $byPlatform[$plat]['engagement'] += (int) $m->engagement;
        }

        arsort($byPost);
        $topPosts = array_map(fn ($pid, $eng) => ['scheduled_post_id' => $pid, 'engagement' => $eng], array_keys($byPost), $byPost);

        return [
            'totals'       => $totals,
            'by_platform'  => $byPlatform,
            'top_posts'    => array_slice($topPosts, 0, 5),
            'tracked_targets' => $latest->count(),
        ];
    }
}
