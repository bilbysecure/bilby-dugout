<?php

declare(strict_types=1);

namespace Tests\Governance;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\DataExport;
use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Domain\Models\Request;
use App\Domain\Models\Subscription;
use App\Policies\AuthorizationException;
use App\Repositories\MediaRepository;
use App\Services\Governance\DataExportService;
use App\Services\Jobs\Handlers\DataExportHandler;
use App\Services\Jobs\QueueService;
use App\Services\Media\LocalMediaStorage;
use App\Services\Media\MediaService;
use PHPUnit\Framework\TestCase;

final class DataExportTest extends TestCase
{
    private string $root;
    private LocalMediaStorage $storage;

    protected function setUp(): void
    {
        DataExport::query()->delete();
        Job::query()->delete();
        Media::query()->delete();
        Request::query()->delete();
        Subscription::query()->delete();
        $this->root = sys_get_temp_dir() . '/bilby_export_' . uniqid();
        $this->storage = new LocalMediaStorage(['root' => $this->root, 'url_secret' => 's', 'base_url' => 'http://x']);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            array_map('unlink', glob($this->root . '/*/*') ?: []);
            array_map('rmdir', glob($this->root . '/*') ?: []);
            @rmdir($this->root);
        }
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'O', role: Role::ClientOwner, clientEmail: $email);
    }

    public function test_request_enqueues_export_job(): void
    {
        $export = (new DataExportService(new QueueService()))->request($this->owner());

        self::assertSame('pending', $export->status);
        self::assertSame(1, Job::where('name', 'data.export')->where('queue', 'governance')->count());
    }

    public function test_non_owner_cannot_request_export(): void
    {
        $staff = new Principal(userId: 2, email: 's@bilbypixel.com', name: 'S', role: Role::AgencyStaff);
        $this->expectException(AuthorizationException::class);
        (new DataExportService(new QueueService()))->request($staff);
    }

    public function test_export_job_compiles_archive_into_a_clean_media_record(): void
    {
        Subscription::create(['client_email' => 'owner@acme.com', 'status' => 'active', 'stripe_customer_id' => 'cus_SECRET']);
        Request::create(['title' => 'A req', 'type' => 'graphic_design', 'status' => 'submitted', 'client_email' => 'owner@acme.com']);

        $export = DataExport::create(['client_email' => 'owner@acme.com', 'requested_by' => 'owner@acme.com', 'status' => 'pending']);
        (new DataExportHandler($this->storage))->handle(new Job(['name' => 'data.export', 'payload' => ['data_export_id' => $export->id]]));

        $export->refresh();
        self::assertSame('ready', $export->status);
        self::assertNotNull($export->media_id);

        $media = Media::find($export->media_id);
        self::assertSame('clean', $media->scan_status, 'export is immediately downloadable');
        self::assertSame('application/json', $media->mime);

        // The archive contains the tenant's data — but NOT the hidden stripe id.
        $json = $this->storage->get($media->path);
        self::assertStringContainsString('A req', $json);
        self::assertStringNotContainsString('cus_SECRET', $json, 'sensitive fields excluded from export');
    }

    public function test_owner_can_get_a_signed_url_for_the_export(): void
    {
        $export = DataExport::create(['client_email' => 'owner@acme.com', 'requested_by' => 'owner@acme.com', 'status' => 'pending']);
        (new DataExportHandler($this->storage))->handle(new Job(['name' => 'data.export', 'payload' => ['data_export_id' => $export->id]]));

        $media = new MediaService($this->storage, new MediaRepository(), new QueueService(), ['signed_ttl' => 300]);
        $result = $media->temporaryUrl($this->owner(), (int) $export->fresh()->media_id);
        self::assertStringContainsString('/media/blob?', $result['url']);
    }
}
