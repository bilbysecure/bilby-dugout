<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Media\MediaService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/** Uploads, tenant-scoped signed URLs, and the local streaming endpoint. */
final class MediaController extends Controller
{
    public function __construct(private readonly MediaService $media)
    {
    }

    /** Upload a file → quarantined `media` row + async scan. */
    public function store(Request $request, Response $response): Response
    {
        $p = $this->principal($request);

        /** @var UploadedFileInterface|null $file */
        $file = ($request->getUploadedFiles()['file'] ?? null);
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('A file field named "file" is required');
        }

        $body = $this->body($request);
        $media = $this->media->upload(
            $p,
            (string) $file->getStream(),
            (string) $file->getClientFilename(),
            (string) $file->getClientMediaType(),
            $body['client_email'] ?? null,
            $body['attachable_type'] ?? null,
            isset($body['attachable_id']) ? (int) $body['attachable_id'] : null,
        );

        return $this->json($response, ['data' => $media->toArray()], 201);
    }

    /** Return a short-lived signed URL for a tenant-visible, clean file. */
    public function show(Request $request, Response $response, array $args): Response
    {
        $result = $this->media->temporaryUrl($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $result]);
    }

    /** Public streaming endpoint — authorized by the signed token, not JWT. */
    public function blob(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $blob = $this->media->serveSignedBlob(
            (string) ($q['p'] ?? ''),
            (int) ($q['e'] ?? 0),
            (string) ($q['s'] ?? ''),
        );

        $response->getBody()->write($blob['contents']);
        return $response
            ->withHeader('Content-Type', $blob['mime'])
            ->withHeader('Content-Disposition', 'inline; filename="' . $blob['name'] . '"')
            ->withHeader('Cache-Control', 'private, max-age=60')
            ->withStatus(200);
    }
}
