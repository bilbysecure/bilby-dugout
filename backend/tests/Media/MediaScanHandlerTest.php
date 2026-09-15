<?php

declare(strict_types=1);

namespace Tests\Media;

use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Services\Jobs\Handlers\MediaScanHandler;
use App\Services\Media\LocalMediaStorage;
use App\Services\Media\StubVirusScanner;
use PHPUnit\Framework\TestCase;
use Tests\Support\InfectedScanner;

final class MediaScanHandlerTest extends TestCase
{
    private string $root;
    private LocalMediaStorage $storage;

    protected function setUp(): void
    {
        Media::query()->delete();
        $this->root = sys_get_temp_dir() . '/bilby_media_scan_' . uniqid();
        $this->storage = new LocalMediaStorage([
            'root' => $this->root, 'url_secret' => 's', 'base_url' => 'http://x',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            array_map('unlink', glob($this->root . '/*/*') ?: []);
            array_map('rmdir', glob($this->root . '/*') ?: []);
            @rmdir($this->root);
        }
    }

    private function pendingMedia(): Media
    {
        $key = 'tenant/' . uniqid() . '.png';
        $this->storage->put($key, 'DATA');
        return Media::create([
            'client_email' => 'owner@acme.com', 'disk' => 'local', 'path' => $key,
            'original_name' => 'a.png', 'mime' => 'image/png', 'size' => 4,
            'scan_status' => 'pending',
        ]);
    }

    private function job(int $mediaId): Job
    {
        return new Job(['name' => 'media.scan', 'payload' => ['media_id' => $mediaId]]);
    }

    public function test_clean_scan_marks_media_servable(): void
    {
        $media = $this->pendingMedia();

        (new MediaScanHandler($this->storage, new StubVirusScanner()))->handle($this->job($media->id));

        self::assertSame('clean', $media->fresh()->scan_status);
    }

    public function test_infected_scan_quarantines_media(): void
    {
        $media = $this->pendingMedia();

        (new MediaScanHandler($this->storage, new InfectedScanner()))->handle($this->job($media->id));

        self::assertSame('infected', $media->fresh()->scan_status);
        self::assertFalse($media->fresh()->isServable(), 'stays unservable');
    }
}
