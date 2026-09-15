<?php

declare(strict_types=1);

namespace Tests\Meta;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\MetaApiLog;
use App\Domain\Models\MetaConnection;
use App\Repositories\MetaConnectionRepository;
use App\Services\Meta\MetaHealthService;
use PHPUnit\Framework\TestCase;

final class MetaHealthTest extends TestCase
{
    private MetaHealthService $svc;

    protected function setUp(): void
    {
        MetaConnection::query()->delete();
        MetaApiLog::query()->delete();
        $this->svc = new MetaHealthService(new MetaConnectionRepository());
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'O', role: Role::ClientOwner, clientEmail: $email);
    }

    public function test_disconnected_when_no_connection(): void
    {
        $health = $this->svc->health($this->owner());
        self::assertSame('disconnected', $health['status']);
        self::assertFalse($health['connected']);
        self::assertSame([], $health['recent_errors']);
    }

    public function test_reports_headroom_status_and_recent_errors(): void
    {
        $conn = MetaConnection::create([
            'client_email' => 'owner@acme.com', 'status' => 'connected',
            'rate_limit_pct' => 70, 'last_success_at' => date('Y-m-d H:i:s'),
            'ad_account_ids' => ['act_1'], 'scopes_granted' => ['ads_read'],
        ]);
        MetaApiLog::create(['meta_connection_id' => $conn->id, 'client_email' => 'owner@acme.com', 'endpoint' => 'v21.0/act_1/insights', 'ok' => false, 'http_status' => 500, 'error_code' => 1, 'error_message' => 'boom']);

        $health = $this->svc->health($this->owner());

        self::assertSame('connected', $health['status']);
        self::assertTrue($health['connected']);
        self::assertSame(30, $health['rate_limit_headroom'], '100 - 70');
        self::assertSame(['ads_read'], $health['scopes_granted']);
        self::assertCount(1, $health['recent_errors']);
        self::assertSame('boom', $health['recent_errors'][0]['error_message']);
    }

    public function test_client_only_sees_their_own_tenant(): void
    {
        MetaConnection::create(['client_email' => 'other@beta.com', 'status' => 'connected', 'rate_limit_pct' => 10]);

        // owner@acme.com has no connection → disconnected, never sees beta's.
        $health = $this->svc->health($this->owner('owner@acme.com'));
        self::assertSame('disconnected', $health['status']);
    }
}
