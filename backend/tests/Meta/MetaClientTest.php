<?php

declare(strict_types=1);

namespace Tests\Meta;

use App\Domain\Models\MetaApiLog;
use App\Domain\Models\MetaConnection;
use App\Services\Meta\Exceptions\MetaAuthException;
use App\Services\Meta\Exceptions\MetaThrottleException;
use App\Services\Meta\MetaClient;
use App\Services\Meta\MetaTokenStore;
use App\Support\Encryptor;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/** Backoff, rate-limit accounting, and typed errors — all with mocked Guzzle. */
final class MetaClientTest extends TestCase
{
    private array $slept = [];

    protected function setUp(): void
    {
        MetaConnection::query()->delete();
        MetaApiLog::query()->delete();
        $this->slept = [];
    }

    /** Build a MetaClient over a queued set of mock responses; captures sleep durations. */
    private function client(array $responses): array
    {
        $mock = new MockHandler($responses);
        $http = new HttpClient(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://graph.test/']);
        $enc = new Encryptor(str_repeat('k', 32));
        $tokens = new MetaTokenStore($enc);

        $conn = MetaConnection::create(['client_email' => 'owner@acme.com', 'status' => 'disconnected']);
        $tokens->store($conn, 'tok');

        $client = new MetaClient($http, $tokens, ['api_version' => 'v21.0'], function (int $s): void {
            $this->slept[] = $s; // no real waiting
        });
        return [$client, $conn->fresh()];
    }

    public function test_success_parses_rate_limit_headroom(): void
    {
        [$client, $conn] = $this->client([
            new Response(200, ['X-App-Usage' => json_encode(['call_count' => 25, 'total_cputime' => 40, 'total_time' => 30])], json_encode(['data' => [['account_id' => 'act_1']]])),
        ]);

        $out = $client->get($conn, '/me/adaccounts');
        self::assertSame('act_1', $out['data'][0]['account_id']);

        $conn->refresh();
        self::assertSame(40, $conn->rate_limit_pct, 'max of the usage figures');
        self::assertSame('connected', $conn->status);
        self::assertNotNull($conn->last_success_at);
    }

    public function test_throttle_then_success_backs_off_exponentially(): void
    {
        [$client, $conn] = $this->client([
            new Response(429, [], json_encode(['error' => ['code' => 4, 'message' => 'App rate limit']])),
            new Response(429, [], json_encode(['error' => ['code' => 4, 'message' => 'App rate limit']])),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $out = $client->get($conn, '/me/adaccounts');
        self::assertSame([], $out['data']);
        self::assertSame([1, 2], $this->slept, 'backoff 1s then 2s before the successful third try');
    }

    public function test_throttle_exhausted_throws_typed_exception(): void
    {
        // 5 throttles > 4 backoff slots → give up. (Meta returns rate-limit codes in the body.)
        $responses = array_fill(0, 5, new Response(400, [], json_encode(['error' => ['code' => 613, 'message' => 'Calls per hour']])));
        [$client, $conn] = $this->client($responses);

        try {
            $client->get($conn, '/act_1/campaigns');
            self::fail('expected throttle exception');
        } catch (MetaThrottleException $e) {
            self::assertSame(613, $e->metaCode);
        }
        self::assertSame([1, 2, 4, 8], $this->slept, 'all four backoff steps used');
        self::assertSame('error', $conn->fresh()->status);
    }

    public function test_invalid_token_raises_auth_exception_and_marks_expired(): void
    {
        [$client, $conn] = $this->client([
            new Response(401, [], json_encode(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']])),
        ]);

        $this->expectException(MetaAuthException::class);
        try {
            $client->get($conn, '/me');
        } finally {
            self::assertSame('expired', $conn->fresh()->status);
        }
    }

    public function test_every_call_is_logged_for_health(): void
    {
        [$client, $conn] = $this->client([new Response(200, [], json_encode(['data' => []]))]);
        $client->get($conn, '/me/adaccounts');

        $log = MetaApiLog::where('client_email', 'owner@acme.com')->first();
        self::assertNotNull($log);
        self::assertTrue((bool) $log->ok);
        self::assertStringContainsString('adaccounts', $log->endpoint);
    }
}
