<?php

declare(strict_types=1);

namespace App\Services\Ai;

/** Credential-free AI stub: deterministic, generated-looking output for the simulation. */
final class MockLlmService implements LlmService
{
    public function draftRequest(array $input): array
    {
        $type = $this->humanize($input['type'] ?? 'creative');
        $platforms = $this->listify($input['platform'] ?? []);
        $objective = $this->humanize($input['objective'] ?? 'engagement');
        $tone = $input['tone'] ?: 'professional';
        $notes = trim((string) ($input['notes'] ?? ''));
        $audience = $input['target_audience'] ?? '';

        $title = $input['title'] ?: ($type . ($platforms ? " for {$platforms}" : ''));

        $description = "Produce a {$type}" . ($platforms ? " optimised for {$platforms}" : '')
            . " with a clear focus on {$objective}. "
            . ($notes ? "Context from the brief: {$notes}. " : '')
            . "Deliverables should be on-brand, polished, and ready for review, including any supporting variations needed for testing.";

        $targetAudience = $audience ?: "Primary decision-makers and engaged followers most likely to respond to {$objective} messaging.";

        $keyMessaging = "Lead with a benefit-driven hook, reinforce the core value proposition, and close with a clear call to action aligned to {$objective}.";

        return [
            'title' => $title,
            'description' => $description,
            'target_audience' => $targetAudience,
            'key_messaging' => $keyMessaging,
            'tone' => $tone,
        ];
    }

    public function recommendations(array $subscription): array
    {
        $plan = $subscription['plan_name'] ?? 'your plan';
        $tier = $subscription['sla_tier'] ?? 'burrow';
        $limit = (int) ($subscription['monthly_request_limit'] ?? 0);
        $services = is_array($subscription['services'] ?? null) ? $subscription['services'] : [];

        $recs = [
            ['title' => 'Batch similar requests', 'detail' => "You're on the {$plan} plan — grouping related briefs (e.g. a full campaign at once) keeps turnaround fast and frees up your monthly allowance."],
            ['title' => 'Use brand kits on every request', 'detail' => 'Attaching a brand kit reduces revision cycles and keeps deliverables consistent across channels.'],
        ];
        if ($limit > 0) {
            $recs[] = ['title' => 'Track your monthly usage', 'detail' => "Your plan includes {$limit} requests per month. Submit high-priority work early in the cycle to stay ahead of the limit."];
        }
        if (in_array('social_media_post', $services, true)) {
            $recs[] = ['title' => 'Plan social content ahead', 'detail' => 'Use the Calendar to schedule posts across channels and let best-time suggestions place them for maximum reach.'];
        }
        $recs[] = ['title' => "Make the most of your {$tier} SLA", 'detail' => 'Flagging requests as high priority helps the team sequence work within your service level.'];

        return $recs;
    }

    public function reviewBrandAsset(array $asset, array $brandKit): array
    {
        $brand = $brandKit['brand_name'] ?? 'the brand';
        $name = $asset['name'] ?? 'the asset';
        return [
            'status' => 'needs_attention',
            'summary' => "\"{$name}\" is broadly consistent with {$brand}, with a few areas to tighten before publishing.",
            'score' => 78,
            'matches' => ['Primary colour usage aligns with the brand palette', 'Overall tone matches the brand voice'],
            'issues' => ['Secondary typography differs from the brand kit', 'Logo clear-space looks slightly tight'],
            'recommendations' => ['Swap the body font to the approved secondary typeface', 'Increase padding around the logo to meet clear-space rules'],
        ];
    }

    private function humanize(string $s): string
    {
        return trim(str_replace('_', ' ', $s));
    }

    private function listify(mixed $v): string
    {
        $arr = is_array($v) ? $v : ($v ? [$v] : []);
        return implode(', ', array_map(fn ($x) => $this->humanize((string) $x), $arr));
    }
}
