<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Domain\Models\BrandAsset;
use App\Domain\Models\BrandKit;

/**
 * Resolves report branding (Master Spec §15): BilbyPixel by default, or
 * per-client WHITE-LABEL using the client's Brand Hub kit (primary colour, brand
 * name, logo). Falls back to the default if white-label is requested but the
 * client has no brand kit.
 */
final class ReportBrandingService
{
    private const DEFAULT = [
        'brand_name'  => 'BilbyPixel',
        'primary'     => '#4B2A85',
        'accent'      => '#6366f1',
        'logo_url'    => null,
        'footer'      => 'Prepared by BilbyPixel',
        'white_label' => false,
    ];

    public function resolve(string $clientEmail, bool $whiteLabel): array
    {
        if (!$whiteLabel) {
            return self::DEFAULT;
        }

        $kit = BrandKit::where('client_email', $clientEmail)->first();
        if (!$kit) {
            return self::DEFAULT; // no brand kit → fall back to BilbyPixel
        }

        $primary = $this->primaryColour($kit) ?? self::DEFAULT['primary'];
        return [
            'brand_name'  => $kit->brand_name ?: self::DEFAULT['brand_name'],
            'primary'     => $primary,
            'accent'      => $primary,
            'logo_url'    => $this->logoUrl($kit),
            'footer'      => 'Prepared for ' . ($kit->brand_name ?: $clientEmail),
            'white_label' => true,
        ];
    }

    private function primaryColour(BrandKit $kit): ?string
    {
        $colors = is_array($kit->colors) ? $kit->colors : (json_decode((string) $kit->colors, true) ?: []);
        foreach ($colors as $c) {
            if (($c['role'] ?? '') === 'primary' && !empty($c['hex'])) {
                return (string) $c['hex'];
            }
        }
        return isset($colors[0]['hex']) ? (string) $colors[0]['hex'] : null;
    }

    private function logoUrl(BrandKit $kit): ?string
    {
        $asset = BrandAsset::where('brand_kit_id', $kit->id)
            ->where(fn ($q) => $q->whereNotNull('logo_variant')->orWhere('category', 'like', '%logo%'))
            ->first();
        return $asset->file_url ?? null;
    }
}
