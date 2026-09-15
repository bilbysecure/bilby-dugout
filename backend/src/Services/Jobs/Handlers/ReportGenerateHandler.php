<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Domain\Models\Report;
use App\Services\Jobs\JobHandler;
use App\Services\Mail\Mailer;
use App\Services\Media\MediaStorage;
use App\Services\Reports\ReportBrandingService;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\ReportRenderer;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Generates a report (Master Spec §15): assemble data → resolve branding →
 * render → store as a clean media record → mark ready (+ optional email).
 */
final class ReportGenerateHandler implements JobHandler
{
    public function __construct(
        private readonly ReportDataService $data,
        private readonly ReportBrandingService $branding,
        private readonly ReportRenderer $renderer,
        private readonly MediaStorage $storage,
        private readonly Mailer $mailer,
        private readonly array $config = [], // settings['reports']
    ) {
    }

    public function handle(Job $job): void
    {
        $id = $job->payload['report_id'] ?? null;
        if ($id === null) {
            return;
        }
        /** @var Report|null $report */
        $report = Report::find($id);
        if (!$report) {
            return;
        }

        $report->status = 'processing';
        $report->save();

        try {
            $data = $this->data->assemble($report);
            $branding = $this->branding->resolve((string) $report->client_email, (bool) $report->white_label);
            $bytes = $this->renderer->render($report->toArray(), $data, $branding);

            $key = 'reports/' . Uuid::uuid4()->toString() . '.' . $this->renderer->extension();
            $this->storage->put($key, $bytes);

            $media = Media::create([
                'client_email'    => $report->client_email,
                'uploaded_by'     => $report->requested_by,
                'disk'            => $this->storage->name(),
                'path'            => $key,
                'original_name'   => 'report-' . $report->type . '-' . date('Ymd') . '.' . $this->renderer->extension(),
                'mime'            => $this->renderer->mimeType(),
                'size'            => strlen($bytes),
                'checksum'        => hash('sha256', $bytes),
                'scan_status'     => 'clean', // system-generated, trusted
                'attachable_type' => Report::class,
                'attachable_id'   => $report->id,
            ]);

            $report->media_id = $media->id;
            $report->status = 'ready';
            $report->generated_at = date('Y-m-d H:i:s');
            $report->save();

            if (!empty($this->config['email_on_ready'])) {
                $this->mailer->send((string) $report->client_email, 'Your report is ready', "Your {$report->type} report is ready to view in BilbyDugout.", 'report_ready');
            }
        } catch (Throwable $e) {
            $report->status = 'failed';
            $report->error = substr($e->getMessage(), 0, 1000);
            $report->save();
            throw $e; // let the worker retry/backoff
        }
    }
}
