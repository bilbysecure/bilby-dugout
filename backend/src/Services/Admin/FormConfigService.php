<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\FormConfig;
use App\Policies\AuthorizationException;
use InvalidArgumentException;

/**
 * Request-form configuration (service types, tones of voice). Stored in
 * form_configs.items; drives the service cards on the request form and,
 * later, the service plans. Agency read; global-admin write.
 */
final class FormConfigService
{
    private const KEYS = ['service_types', 'tones_of_voice'];

    public function get(Principal $p, string $key): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        $this->assertKey($key);
        $row = FormConfig::where('config_key', $key)->first();
        $items = $row?->items ?? $this->defaults($key);
        return $key === 'service_types' ? $this->withLocked($items) : $items;
    }

    public function set(Principal $p, string $key, array $items): array
    {
        $this->assertAdmin($p);
        $this->assertKey($key);
        if ($key === 'service_types') {
            $items = $this->withLocked($items); // locked defaults can't be edited or removed
        }
        $row = FormConfig::firstOrNew(['config_key' => $key]);
        $row->items = array_values($items);
        $row->save();
        return $row->items;
    }

    /** Ensure the canonical locked service types are always present (once) at the end. */
    private function withLocked(array $items): array
    {
        $locked = array_values(array_filter($this->defaultServiceTypes(), fn ($x) => !empty($x['locked'])));
        $unlocked = array_values(array_filter($items, fn ($x) => empty($x['locked'])));
        return array_merge($unlocked, $locked);
    }

    public function reset(Principal $p, string $key): array
    {
        return $this->set($p, $key, $this->defaults($key));
    }

    public function defaults(string $key): array
    {
        return $key === 'tones_of_voice' ? $this->defaultTones() : $this->defaultServiceTypes();
    }

    // ── defaults ─────────────────────────────────────────────
    private function defaultServiceTypes(): array
    {
        $st = fn (string $label, string $desc, array $subs, array $plats = [], array $objs = []) => [
            'label' => $label,
            'description' => $desc,
            'sub_types' => $subs,
            'platforms' => array_map(fn ($n) => ['name' => $n, 'icon' => ''], $plats),
            'objectives' => array_map(fn ($n) => ['name' => $n, 'icon' => ''], $objs),
        ];

        return [
            $st('Social Media Post', 'Managing your profiles, engagement, and reputation to keep your brand active and credible.',
                ['Static Post', 'Carousel', 'Story', 'Reel', 'Short Video', 'Poll/Interactive Graphic', 'Meme Content', 'UGC (User Generated Content) Edit', 'Livestream Graphics', 'Animated Post', 'Countdown Series', 'Pinned Post Design'],
                ['Instagram', 'Facebook', 'TikTok', 'LinkedIn', 'Pinterest', 'X (Twitter)', 'Youtube'],
                ['Engagement', 'Direct Traffic', 'Product Showcase']),
            $st('Graphic Design', 'Professional custom visuals and layouts for digital platforms and business use.',
                ['Infographic', 'Illustration', 'Web Banner', 'Social/Canva Template', 'Pitch Deck', 'E-book Layout', 'Lead Magnet', 'Email Signature', 'Podcast Art', 'YouTube Thumbnails']),
            $st('Brand Asset', "Core identity elements including logos and style guides to define your brand's look.",
                ['Logo', 'Brand Guidelines', 'Icon Set', 'Style Guide']),
            $st('Print & Specialised', 'High-quality design for physical materials and marketing collateral.',
                ['Flyer', 'Brochure', 'Poster', 'Signage', 'Business Card']),
            $st('Video Production', 'High-impact video content for storytelling, demos, and social media growth.',
                ['Explainer Video', 'Ad Spot', 'Testimonial', 'Animation', 'Social Reel']),
            $st('Ads Campaign', 'Targeted paid advertising strategies to maximize reach and sales conversions.',
                ['Meta Ads', 'Google Display', 'LinkedIn Ads', 'TikTok Ads']),
            $st('Content Writing', 'Written content that informs, engages, and converts your audience.',
                ['Blog Post', 'Website Copy', 'Email Copy', 'Ad Copy', 'Script']),
            $st('Website Update', 'Updates and improvements to your website pages and content.',
                ['Landing Page', 'Homepage Update', 'New Section', 'Bug Fix']),
            $st('SEO Content', 'Search-optimised content to grow organic traffic.',
                ['SEO Article', 'Keyword Research', 'Meta Descriptions', 'Content Refresh']),
            [
                'label' => 'Other / Custom',
                'description' => 'Have a unique project in mind? Use this option for specialized requests that fall outside our standard service categories.',
                'sub_types' => [],
                'platforms' => [],
                'objectives' => [],
                'locked' => true,
            ],
        ];
    }

    private function defaultTones(): array
    {
        return array_map(fn ($t) => ['label' => $t], ['Professional', 'Friendly', 'Bold', 'Playful', 'Luxury', 'Educational', 'Urgent']);
    }

    private function assertKey(string $key): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException('Unknown config key');
        }
    }

    private function assertAdmin(Principal $p): void
    {
        if ($p->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
    }
}
