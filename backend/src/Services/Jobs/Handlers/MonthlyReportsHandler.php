<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\Report;
use App\Domain\Models\Subscription;
use App\Services\Jobs\JobHandler;

/**
 * Scheduled monthly report generation (Master Spec §15). Enqueues last month's
 * social-performance report for each active client. Idempotent — skips a client
 * that already has a report for the period, so repeated scheduler ticks are safe.
 */
final class MonthlyReportsHandler implements JobHandler
{
    public function __construct(private readonly \App\Services\Jobs\QueueService $queue)
    {
    }

    public function handle(Job $job): void
    {
        $start = date('Y-m-01', strtotime('first day of last month'));
        $end = date('Y-m-t', strtotime('last day of last month'));

        foreach (Subscription::where('status', 'active')->get() as $sub) {
            $client = (string) $sub->client_email;
            $exists = Report::where('client_email', $client)
                ->where('type', 'social_performance')
                ->where('period_start', $start)
                ->exists();
            if ($exists) {
                continue;
            }

            $report = Report::create([
                'client_email' => $client,
                'requested_by' => 'system',
                'type'         => 'social_performance',
                'period_start' => $start,
                'period_end'   => $end,
                'status'       => 'pending',
            ]);
            $this->queue->enqueue('report.generate', ['report_id' => $report->id], 0, $client, 'reports');
        }
    }
}
