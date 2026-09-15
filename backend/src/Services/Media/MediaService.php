<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Auth\Principal;
use App\Domain\Models\Media;
use App\Repositories\MediaRepository;
use App\Services\Jobs\QueueService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Upload pipeline + secure serving (Master Spec §17.2).
 *
 * Upload: allowlist + per-type size check → store via the driver → create a
 * `media` row (scan_status=pending) → enqueue an async virus scan (+ a thumbnail
 * job for images). Files stay quarantined and unservable until scanned clean.
 *
 * Serving: only tenant-visible, clean media yields a short-lived signed URL —
 * never a raw storage location.
 */
final class MediaService
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly MediaRepository $repo,
        private readonly QueueService $queue,
        private readonly array $config,
    ) {
    }

    /**
     * Validate + store an upload, then enqueue scanning/derivatives.
     *
     * @param string      $originalName client filename (for ext + display)
     * @param string      $clientMime   client-declared MIME (validated against ext)
     * @param string|null $clientEmail  tenant; agency may pass one, clients are forced to own
     */
    public function upload(
        Principal $p,
        string $contents,
        string $originalName,
        string $clientMime,
        ?string $clientEmail = null,
        ?string $attachableType = null,
        ?int $attachableId = null,
    ): Media {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $this->assertAllowed($ext, $clientMime, strlen($contents));

        // Clients are pinned to their own tenant; agency may target a client (or null = agency-owned).
        $tenant = $p->isClient() ? (string) $p->clientEmail : ($clientEmail ?: ($p->impersonatedClientEmail ?? null));

        $key = $this->buildKey($tenant, $ext);
        $this->storage->put($key, $contents);

        $media = Media::create([
            'client_email'    => $tenant,
            'uploaded_by'     => $p->email,
            'disk'            => $this->storage->name(),
            'path'            => $key,
            'original_name'   => $originalName,
            'mime'            => $clientMime,
            'size'            => strlen($contents),
            'checksum'        => hash('sha256', $contents),
            'scan_status'     => 'pending',
            'attachable_type' => $attachableType,
            'attachable_id'   => $attachableId,
        ]);

        // Async: scan first; images also get a thumbnail/derivative job.
        $this->queue->enqueue('media.scan', ['media_id' => $media->id], 0, $tenant, 'media');
        if (in_array($ext, $this->config['image_exts'] ?? [], true)) {
            $this->queue->enqueue('media.thumbnail', ['media_id' => $media->id], 0, $tenant, 'media');
        }

        return $media;
    }

    /**
     * A short-lived signed URL for a tenant-visible, clean file.
     * Throws ModelNotFound (→404) for cross-tenant/unknown ids — never leaks existence.
     */
    public function temporaryUrl(Principal $p, int $mediaId, ?int $ttl = null): array
    {
        $media = $this->repo->find($p, $mediaId);
        if (!$media) {
            throw new ModelNotFoundException('Media not found');
        }
        if (!$media->isServable()) {
            throw new InvalidArgumentException('Media is not available (scan status: ' . $media->scan_status . ')');
        }

        $ttl ??= (int) ($this->config['signed_ttl'] ?? 300);
        return [
            'url'        => $this->storage->signedUrl($media->path, $ttl),
            'expires_at' => date('c', time() + $ttl),
            'media'      => $media->toArray(),
        ];
    }

    /**
     * Resolve a signed blob request (local driver streaming endpoint).
     * Verifies the signature AND re-checks the file is clean (defence in depth).
     *
     * @return array{contents:string, mime:string, name:string}
     */
    public function serveSignedBlob(string $key, int $expires, string $sig): array
    {
        if (!$this->storage instanceof LocalMediaStorage || !$this->storage->verify($key, $expires, $sig)) {
            throw new InvalidArgumentException('Invalid or expired signature');
        }

        $media = Media::where('disk', 'local')->where('path', $key)->first();
        if (!$media || !$media->isServable()) {
            throw new ModelNotFoundException('Media not available');
        }

        return [
            'contents' => $this->storage->get($key),
            'mime'     => (string) ($media->mime ?: 'application/octet-stream'),
            'name'     => (string) ($media->original_name ?: basename($key)),
        ];
    }

    // ── internals ────────────────────────────────────────────────

    private function assertAllowed(string $ext, string $mime, int $size): void
    {
        $allowed = $this->config['allowed'] ?? [];
        if (!isset($allowed[$ext])) {
            throw new InvalidArgumentException("File type .{$ext} is not allowed");
        }
        if ($mime !== '' && !in_array($mime, $allowed[$ext], true)) {
            throw new InvalidArgumentException("MIME {$mime} does not match .{$ext}");
        }
        $group = $this->group($ext);
        $max = $this->config['max_bytes'][$group] ?? $this->config['max_bytes']['default'] ?? (10 * 1024 * 1024);
        if ($size > $max) {
            throw new InvalidArgumentException("File exceeds the {$group} size limit");
        }
        if ($size <= 0) {
            throw new InvalidArgumentException('Empty file');
        }
    }

    private function group(string $ext): string
    {
        if (in_array($ext, $this->config['image_exts'] ?? [], true)) {
            return 'image';
        }
        return match ($ext) {
            'pdf' => 'document',
            'mp4', 'mov' => 'video',
            default => 'default',
        };
    }

    private function buildKey(?string $tenant, string $ext): string
    {
        $prefix = $tenant ? substr(hash('sha256', $tenant), 0, 16) : 'agency';
        return $prefix . '/' . Uuid::uuid4()->toString() . '.' . $ext;
    }
}
