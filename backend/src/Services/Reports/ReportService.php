<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Auth\Principal;
use App\Domain\Models\Report;
use App\Policies\AuthorizationException;
use App\Repositories\ReportRepository;
use App\Services\Jobs\QueueService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Client-facing reports (Master Spec §15): request → generate job → download. */
final class ReportService
{
    public function __construct(
        private readonly ReportRepository $repo,
        private readonly QueueService $queue,
    ) {
    }

    public function list(Principal $p): array
    {
        return $this->repo->visibleTo($p)->orderByDesc('created_at')->limit(50)->get()->toArray();
    }

    public function get(Principal $p, int $id): Report
    {
        $report = $this->repo->find($p, $id);
        if (!$report) {
            throw new ModelNotFoundException('Report not found');
        }
        return $report;
    }

    /** Request a report — clients for themselves, managers on a client's behalf. */
    public function request(Principal $p, array $data): Report
    {
        $type = in_array($data['type'] ?? '', Report::TYPES, true) ? $data['type'] : 'social_performance';

        // Tenant: a client is pinned to their own; a manager targets a client.
        $client = $p->isClient()
            ? (string) $p->clientEmail
            : (string) ($data['client_email'] ?? $p->impersonatedClientEmail ?? '');
        if ($client === '') {
            throw new InvalidArgumentException('client_email is required');
        }
        if (!$p->isManager() && !$p->isClient()) {
            throw new AuthorizationException('Not allowed to request reports');
        }

        $report = Report::create([
            'client_email' => $client,
            'requested_by' => $p->email,
            'type'         => $type,
            'period_start' => $data['period_start'] ?? null,
            'period_end'   => $data['period_end'] ?? null,
            'params'       => $data['params'] ?? [],
            'white_label'  => (bool) ($data['white_label'] ?? false),
            'status'       => 'pending',
        ]);
        $this->queue->enqueue('report.generate', ['report_id' => $report->id], 0, $client, 'reports');

        return $report;
    }
}
