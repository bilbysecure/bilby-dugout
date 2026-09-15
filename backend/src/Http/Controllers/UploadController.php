<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Policies\AuthorizationException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

/** Simple image upload → public/uploads, returns an absolute URL. */
final class UploadController extends Controller
{
    private const ALLOWED = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    public function __construct(private readonly array $settings)
    {
    }

    public function store(Request $request, Response $response): Response
    {
        $p = $this->principal($request);
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }

        /** @var UploadedFileInterface|null $file */
        $file = ($request->getUploadedFiles()['file'] ?? null);
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('A file field named "file" is required');
        }

        $ext = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION) ?: 'png');
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Only image files are allowed');
        }

        $dir = dirname(__DIR__, 3) . '/public/uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        $file->moveTo($dir . '/' . $name);

        $url = rtrim($this->settings['app']['url'] ?? '', '/') . '/uploads/' . $name;
        return $this->json($response, ['url' => $url]);
    }
}
