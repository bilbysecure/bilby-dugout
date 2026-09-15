<?php

declare(strict_types=1);

namespace Tests\Media;

use App\Services\Media\LocalMediaStorage;
use PHPUnit\Framework\TestCase;

final class LocalMediaStorageTest extends TestCase
{
    private string $root;
    private LocalMediaStorage $storage;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bilby_media_' . uniqid();
        $this->storage = new LocalMediaStorage([
            'root' => $this->root,
            'url_secret' => 'test-secret',
            'base_url' => 'http://localhost:8080',
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

    public function test_put_exists_get_delete_roundtrip(): void
    {
        $key = 'tenant/file.png';
        self::assertFalse($this->storage->exists($key));

        $this->storage->put($key, 'BYTES');
        self::assertTrue($this->storage->exists($key));
        self::assertSame('BYTES', $this->storage->get($key));

        $this->storage->delete($key);
        self::assertFalse($this->storage->exists($key));
    }

    public function test_signed_url_is_app_url_and_verifies(): void
    {
        $key = 'tenant/file.png';
        $url = $this->storage->signedUrl($key, 300);

        self::assertStringStartsWith('http://localhost:8080/api/v1/media/blob?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        self::assertSame($key, $q['p']);
        self::assertTrue($this->storage->verify($q['p'], (int) $q['e'], $q['s']));
    }

    public function test_verify_rejects_tampering_and_expiry(): void
    {
        $key = 'tenant/file.png';
        $expires = time() + 300;
        $sig = null;
        parse_str(parse_url($this->storage->signedUrl($key, 300), PHP_URL_QUERY), $q);
        $sig = $q['s'];

        self::assertFalse($this->storage->verify($key, $expires, 'deadbeef'), 'bad signature');
        self::assertFalse($this->storage->verify('tenant/other.png', $expires, $sig), 'wrong key');
        self::assertFalse($this->storage->verify($key, time() - 10, $sig), 'expired');
    }

    public function test_key_traversal_is_blocked(): void
    {
        $this->expectExceptionMessage('Invalid media key');
        $this->storage->put('../../etc/passwd', 'x');
    }

    public function test_name(): void
    {
        self::assertSame('local', $this->storage->name());
    }
}
