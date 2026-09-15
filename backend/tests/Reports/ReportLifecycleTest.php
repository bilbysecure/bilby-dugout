<?php

declare(strict_types=1);

namespace Tests\Reports;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Job;
use App\Domain\Models\Report;
use App\Repositories\ReportRepository;
use App\Services\Jobs\QueueService;
use App\Services\Reports\ReportService;
use PHPUnit\Framework\TestCase;

final class ReportLifecycleTest extends TestCase
{
    private ReportService $svc;

    protected function setUp(): void
    {
        Report::query()->delete();
        Job::query()->delete();
        $this->svc = new ReportService(new ReportRepository(), new QueueService());
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'O', role: Role::ClientOwner, clientEmail: $email);
    }

    public function test_request_creates_pending_report_and_enqueues_generation(): void
    {
        $report = $this->svc->request($this->owner(), ['type' => 'social_performance', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31']);

        self::assertSame('pending', $report->status);
        self::assertSame('owner@acme.com', $report->client_email);
        self::assertSame(1, Job::where('name', 'report.generate')->where('queue', 'reports')->count());
    }

    public function test_invalid_type_falls_back_to_social_performance(): void
    {
        $report = $this->svc->request($this->owner(), ['type' => 'nonsense']);
        self::assertSame('social_performance', $report->type);
    }

    public function test_client_is_pinned_to_own_tenant(): void
    {
        // A client cannot request a report for another tenant even if they pass one.
        $report = $this->svc->request($this->owner('owner@acme.com'), ['client_email' => 'other@beta.com', 'type' => 'delivery_summary']);
        self::assertSame('owner@acme.com', $report->client_email);
    }

    public function test_list_is_tenant_scoped(): void
    {
        Report::create(['client_email' => 'other@beta.com', 'type' => 'social_performance', 'status' => 'ready']);
        $this->svc->request($this->owner(), ['type' => 'social_performance']);

        $mine = $this->svc->list($this->owner());
        self::assertCount(1, $mine);
        self::assertSame('owner@acme.com', $mine[0]['client_email']);
    }
}
