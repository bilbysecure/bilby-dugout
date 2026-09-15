<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\BrandKit;
use App\Domain\Models\ConsentRecord;
use App\Domain\Models\DataExport;
use App\Domain\Models\Invoice;
use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Domain\Models\Project;
use App\Domain\Models\Request;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\Subscription;
use App\Domain\Models\Task;
use App\Services\Jobs\JobHandler;
use App\Services\Media\MediaStorage;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Compiles a tenant's data into a JSON archive (Master Spec §17.4), stored as a
 * clean media record the owner can download via a signed URL. Sensitive fields
 * (e.g. stripe_customer_id) are excluded because the models hide them from
 * serialization.
 */
final class DataExportHandler implements JobHandler
{
    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function handle(Job $job): void
    {
        $id = $job->payload['data_export_id'] ?? null;
        if ($id === null) {
            return;
        }
        /** @var DataExport|null $export */
        $export = DataExport::find($id);
        if (!$export) {
            return;
        }

        $export->status = 'processing';
        $export->save();

        try {
            $client = (string) $export->client_email;
            $archive = [
                'generated_at'    => date('c'),
                'client_email'    => $client,
                'requests'        => Request::where('client_email', $client)->get()->toArray(),
                'subscriptions'   => Subscription::where('client_email', $client)->get()->toArray(),   // stripe_customer_id hidden
                'invoices'        => Invoice::where('client_email', $client)->get()->toArray(),          // stripe_customer_id hidden
                'brand_kits'      => BrandKit::where('client_email', $client)->get()->toArray(),
                'projects'        => Project::where('client_email', $client)->get()->toArray(),
                'tasks'           => Task::where('client_email', $client)->get()->toArray(),
                'scheduled_posts' => ScheduledPost::where('client_email', $client)->get()->toArray(),
                'consent_records' => ConsentRecord::where('client_email', $client)->get()->toArray(),
            ];
            $json = (string) json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $key = 'exports/' . Uuid::uuid4()->toString() . '.json';
            $this->storage->put($key, $json);

            $media = Media::create([
                'client_email'    => $client,
                'uploaded_by'     => $export->requested_by,
                'disk'            => $this->storage->name(),
                'path'            => $key,
                'original_name'   => 'bilbyhub-export-' . date('Ymd-His') . '.json',
                'mime'            => 'application/json',
                'size'            => strlen($json),
                'checksum'        => hash('sha256', $json),
                'scan_status'     => 'clean', // system-generated, trusted (not a user upload)
                'attachable_type' => DataExport::class,
                'attachable_id'   => $export->id,
            ]);

            $export->media_id = $media->id;
            $export->status = 'ready';
            $export->save();
        } catch (Throwable $e) {
            $export->status = 'failed';
            $export->error = substr($e->getMessage(), 0, 1000);
            $export->save();
            throw $e; // let the worker apply retry/backoff
        }
    }
}
