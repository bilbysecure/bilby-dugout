<?php

declare(strict_types=1);

namespace Tests\Media;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Repositories\MediaRepository;
use App\Services\Jobs\QueueService;
use App\Services\Media\LocalMediaStorage;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MediaServiceTest extends TestCase
{
    private string $root;
    private MediaService $service;

    protected function setUp(): void
    {
        Media::query()->delete();
        Job::query()->delete();

        $this->root = sys_get_temp_dir() . '/bilby_media_svc_' . uniqid();
        $storage = new LocalMediaStorage([
            'root' => $this->root, 'url_secret' => 'test-secret', 'base_url' => 'http://localhost:8080',
        ]);
        $this->service = new MediaService($storage, new MediaRepository(), new QueueService(), $this->config());
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            array_map('unlink', glob($this->root . '/*/*') ?: []);
            array_map('rmdir', glob($this->root . '/*') ?: []);
            @rmdir($this->root);
        }
    }

    private function config(): array
    {
        return [
            'signed_ttl' => 300,
            'allowed'    => ['png' => ['image/png'], 'pdf' => ['application/pdf']],
            'max_bytes'  => ['image' => 1024, 'document' => 2048, 'default' => 1024],
            'image_exts' => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
        ];
    }

    private function client(string $email): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'C', role: Role::ClientOwner, clientEmail: $email);
    }

    public function test_upload_quarantines_and_enqueues_scan_and_thumbnail(): void
    {
        $p = $this->client('owner@acme.com');
        $media = $this->service->upload($p, 'PNGDATA', 'logo.png', 'image/png');

        self::assertSame('pending', $media->scan_status, 'quarantined until scanned');
        self::assertSame('owner@acme.com', $media->client_email);
        self::assertSame('owner@acme.com', $media->uploaded_by);
        self::assertSame('local', $media->disk);
        self::assertSame(hash('sha256', 'PNGDATA'), $media->checksum);

        self::assertSame(1, Job::where('name', 'media.scan')->count());
        self::assertSame(1, Job::where('name', 'media.thumbnail')->count(), 'images get a derivative job');
    }

    public function test_non_image_does_not_enqueue_thumbnail(): void
    {
        $this->service->upload($this->client('owner@acme.com'), '%PDF-1.4', 'brief.pdf', 'application/pdf');

        self::assertSame(1, Job::where('name', 'media.scan')->count());
        self::assertSame(0, Job::where('name', 'media.thumbnail')->count());
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->upload($this->client('owner@acme.com'), 'MZ', 'evil.exe', 'application/octet-stream');
    }

    public function test_mime_must_match_extension(): void
    {
        $this->expectExceptionMessage('does not match');
        $this->service->upload($this->client('owner@acme.com'), 'x', 'logo.png', 'text/plain');
    }

    public function test_size_limit_is_enforced(): void
    {
        $this->expectExceptionMessage('size limit');
        $this->service->upload($this->client('owner@acme.com'), str_repeat('a', 2000), 'big.png', 'image/png');
    }

    public function test_signed_url_is_gated_on_clean_scan(): void
    {
        $p = $this->client('owner@acme.com');
        $media = $this->service->upload($p, 'PNGDATA', 'logo.png', 'image/png');

        // pending → not servable
        try {
            $this->service->temporaryUrl($p, $media->id);
            self::fail('expected pending media to be unservable');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('not available', $e->getMessage());
        }

        // clean → signed URL
        $media->scan_status = 'clean';
        $media->save();
        $result = $this->service->temporaryUrl($p, $media->id);
        self::assertStringContainsString('/api/v1/media/blob?', $result['url']);
        self::assertArrayHasKey('expires_at', $result);
        self::assertArrayNotHasKey('path', $result['media'], 'raw path never serialized');
    }

    public function test_signed_url_is_tenant_scoped(): void
    {
        $a = $this->client('a@acme.com');
        $media = $this->service->upload($a, 'PNGDATA', 'logo.png', 'image/png');
        $media->scan_status = 'clean';
        $media->save();

        $b = $this->client('b@beta.com');
        $this->expectException(ModelNotFoundException::class);
        $this->service->temporaryUrl($b, $media->id); // cross-tenant → 404, never leaks
    }
}
