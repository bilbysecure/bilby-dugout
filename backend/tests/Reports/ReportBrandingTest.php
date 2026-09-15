<?php

declare(strict_types=1);

namespace Tests\Reports;

use App\Domain\Models\BrandKit;
use App\Services\Reports\ReportBrandingService;
use PHPUnit\Framework\TestCase;

final class ReportBrandingTest extends TestCase
{
    private ReportBrandingService $svc;

    protected function setUp(): void
    {
        BrandKit::query()->delete();
        $this->svc = new ReportBrandingService();
    }

    public function test_default_is_bilbypixel_branding(): void
    {
        $b = $this->svc->resolve('owner@acme.com', false);
        self::assertSame('BilbyPixel', $b['brand_name']);
        self::assertFalse($b['white_label']);
        self::assertSame('#4B2A85', $b['primary']);
    }

    public function test_white_label_uses_client_brand_kit(): void
    {
        BrandKit::create([
            'client_email' => 'owner@acme.com',
            'brand_name'   => 'Acme Co',
            'colors'       => [['role' => 'secondary', 'hex' => '#000000'], ['role' => 'primary', 'hex' => '#ff5722']],
        ]);

        $b = $this->svc->resolve('owner@acme.com', true);
        self::assertTrue($b['white_label']);
        self::assertSame('Acme Co', $b['brand_name']);
        self::assertSame('#ff5722', $b['primary'], 'picks the primary-role colour');
    }

    public function test_white_label_without_a_kit_falls_back_to_default(): void
    {
        $b = $this->svc->resolve('noKit@acme.com', true);
        self::assertSame('BilbyPixel', $b['brand_name']);
        self::assertFalse($b['white_label']);
    }
}
