<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Domain\Models\AdVariant;
use App\Domain\Models\CampaignMetricDaily;

/**
 * A/B comparison math for ad variants vs the control (Master Spec §14).
 *
 * CAUTIOUS by design: a variant is only ever labelled "leading"/"trailing"
 * (directional) until the sample is meaningful AND a two-proportion z-test on
 * the lead conversion rate is significant — only then does it become a
 * "significant_winner"/"significant_loser". No winner is declared early.
 */
final class AbComparisonService
{
    private const MIN_IMPRESSIONS = 1000; // per arm
    private const MIN_TOTAL_LEADS = 25;   // control + variant
    private const Z_95 = 1.96;

    /** @return array<int,array<string,mixed>> one row per variant in the phase */
    public function compare(int $phaseId): array
    {
        $variants = AdVariant::where('phase_id', $phaseId)->get();
        if ($variants->isEmpty()) {
            return [];
        }

        $stats = [];
        foreach ($variants as $v) {
            $stats[$v->id] = $this->aggregate((string) $v->meta_ad_id) + [
                'variant_id' => $v->id,
                'headline'   => $v->headline,
                'is_control' => (bool) $v->is_control,
            ];
        }

        $control = null;
        foreach ($stats as $s) {
            if ($s['is_control']) {
                $control = $s;
                break;
            }
        }

        $out = [];
        foreach ($stats as $s) {
            $out[] = $s['is_control'] || $control === null
                ? $s + ['status' => 'control', 'significance' => 'baseline', 'lift_pct' => 0.0]
                : $s + $this->evaluate($s, $control);
        }
        return $out;
    }

    private function aggregate(string $adId): array
    {
        $rows = CampaignMetricDaily::where('entity_type', 'ad')->where('entity_id', $adId)->get();
        $impr = (int) $rows->sum('impressions');
        $clicks = (int) $rows->sum('clicks');
        $leads = (int) $rows->sum('leads');
        $spend = (float) $rows->sum('spend');

        return [
            'impressions'     => $impr,
            'clicks'          => $clicks,
            'leads'           => $leads,
            'spend'           => round($spend, 2),
            'ctr'             => $impr > 0 ? round($clicks / $impr * 100, 2) : 0.0,
            'cpl'             => $leads > 0 ? round($spend / $leads, 2) : 0.0,
            'conversion_rate' => $impr > 0 ? $leads / $impr : 0.0,
        ];
    }

    private function evaluate(array $v, array $c): array
    {
        $cr = $v['conversion_rate'];
        $crC = $c['conversion_rate'];
        $lift = $crC > 0 ? round(($cr - $crC) / $crC * 100, 1) : 0.0;
        $directional = $cr >= $crC ? 'leading' : 'trailing';

        // Not enough data → directional only (never a "winner").
        $enough = $v['impressions'] >= self::MIN_IMPRESSIONS
            && $c['impressions'] >= self::MIN_IMPRESSIONS
            && ($v['leads'] + $c['leads']) >= self::MIN_TOTAL_LEADS;

        if (!$enough) {
            return ['status' => $directional, 'significance' => 'insufficient_data', 'lift_pct' => $lift];
        }

        $z = $this->zScore($v['leads'], $v['impressions'], $c['leads'], $c['impressions']);
        if (abs($z) >= self::Z_95) {
            return [
                'status'       => $cr > $crC ? 'significant_winner' : 'significant_loser',
                'significance' => 'significant',
                'lift_pct'     => $lift,
                'z'            => round($z, 2),
            ];
        }
        return ['status' => $directional, 'significance' => 'not_significant', 'lift_pct' => $lift, 'z' => round($z, 2)];
    }

    /** Two-proportion z-test on conversion rate. */
    private function zScore(int $leadsA, int $imprA, int $leadsB, int $imprB): float
    {
        if ($imprA === 0 || $imprB === 0) {
            return 0.0;
        }
        $pA = $leadsA / $imprA;
        $pB = $leadsB / $imprB;
        $pPool = ($leadsA + $leadsB) / ($imprA + $imprB);
        $se = sqrt($pPool * (1 - $pPool) * (1 / $imprA + 1 / $imprB));
        return $se > 0 ? ($pA - $pB) / $se : 0.0;
    }
}
