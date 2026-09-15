<?php

declare(strict_types=1);

namespace Tests\Governance;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\ActivityLog;
use App\Domain\Models\ConsentRecord;
use App\Services\ActivityLogger;
use App\Services\Governance\ConsentService;
use PHPUnit\Framework\TestCase;

final class ConsentAndAuditTest extends TestCase
{
    protected function setUp(): void
    {
        ConsentRecord::query()->delete();
        ActivityLog::query()->delete();
    }

    public function test_consent_records_privacy_and_terms_versions(): void
    {
        $svc = new ConsentService(['privacy_version' => '2026-01', 'terms_version' => '2026-02']);
        $rows = $svc->record('owner@acme.com', 'owner@acme.com', 'signup');

        self::assertCount(2, $rows);
        $byType = ConsentRecord::where('user_email', 'owner@acme.com')->pluck('version', 'policy_type');
        self::assertSame('2026-01', $byType['privacy']);
        self::assertSame('2026-02', $byType['terms']);
        self::assertSame('signup', ConsentRecord::first()->context);
    }

    public function test_activity_log_redacts_sensitive_metadata(): void
    {
        $actor = new Principal(userId: 1, email: 'admin@bilbypixel.com', name: 'Ava', role: Role::GlobalAdmin);
        (new ActivityLogger())->log($actor, 'thing_happened', null, null, null, 'account', [
            'client_email'       => 'owner@acme.com',
            'password'           => 'hunter2',
            'stripe_customer_id' => 'cus_LEAK',
            'nested'             => ['signature' => 'should-vanish', 'ok' => 'keep'],
        ]);

        $meta = ActivityLog::first()->metadata; // 'array' cast
        self::assertSame('owner@acme.com', $meta['client_email'], 'non-sensitive kept');
        self::assertSame('[redacted]', $meta['password']);
        self::assertSame('[redacted]', $meta['stripe_customer_id']);
        self::assertSame('[redacted]', $meta['nested']['signature'], 'redaction is recursive');
        self::assertSame('keep', $meta['nested']['ok']);
    }
}
