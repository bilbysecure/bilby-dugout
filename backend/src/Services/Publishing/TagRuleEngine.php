<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\ContentTagRule;

/**
 * Content-tag automation. Given a post's text/platforms, applies enabled rules
 * (by priority) to derive additional tags and suggested channels.
 */
final class TagRuleEngine
{
    /**
     * @param string[] $platforms
     * @return array{tags:string[],channel_ids:int[]}
     */
    public function apply(string $clientEmail, string $text, array $platforms = [], array $existingTags = []): array
    {
        $rules = ContentTagRule::where('client_email', $clientEmail)
            ->where('enabled', true)->orderByDesc('priority')->get();

        $tags = array_map('strval', $existingTags);
        $channels = [];
        $haystack = mb_strtolower($text);

        foreach ($rules as $rule) {
            if ($this->matches($rule, $haystack, $platforms)) {
                foreach ((array) ($rule->add_tags ?? []) as $t) {
                    $tags[] = (string) $t;
                }
                foreach ((array) ($rule->add_channel_ids ?? []) as $c) {
                    $channels[] = (int) $c;
                }
            }
        }

        return [
            'tags'        => array_values(array_unique(array_filter($tags))),
            'channel_ids' => array_values(array_unique($channels)),
        ];
    }

    private function matches(ContentTagRule $rule, string $haystackLower, array $platforms): bool
    {
        $pattern = mb_strtolower((string) $rule->pattern);
        return match ($rule->match_type) {
            'all'      => true,
            'keyword'  => $pattern !== '' && str_contains($haystackLower, $pattern),
            'hashtag'  => $pattern !== '' && str_contains($haystackLower, '#' . ltrim($pattern, '#')),
            'platform' => in_array($pattern, array_map('mb_strtolower', $platforms), true),
            default    => false,
        };
    }
}
