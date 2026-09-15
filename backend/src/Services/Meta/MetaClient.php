<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Domain\Models\MetaApiLog;
use App\Domain\Models\MetaConnection;
use App\Services\Meta\Exceptions\MetaAuthException;
use App\Services\Meta\Exceptions\MetaException;
use App\Services\Meta\Exceptions\MetaThrottleException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\ResponseInterface;

/**
 * Reusable Meta Graph / Marketing API client (Master Spec §16). Deliberately
 * consumer-agnostic — publishing, metrics and ad monitoring all call through
 * here. Provides: token injection, rate-limit accounting, exponential backoff on
 * throttling, typed errors, and per-call health logging.
 *
 * The HTTP client + sleeper are injected so tests can mock responses and skip
 * real waits.
 */
final class MetaClient
{
    /** Meta error codes that mean "throttled" (app/user/account rate limits). */
    private const RATE_LIMIT_CODES = [4, 17, 32, 341, 613];
    /** Backoff schedule (seconds) for throttled retries. */
    private const BACKOFF = [1, 2, 4, 8];

    /** @var callable */
    private $sleeper;

    public function __construct(
        private readonly HttpClient $http,
        private readonly MetaTokenStore $tokens,
        private readonly array $config,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static fn (int $s) => sleep($s);
    }

    /** GET a Graph/Marketing resource for a connection (report-only usage). */
    public function get(MetaConnection $conn, string $path, array $query = []): array
    {
        return $this->call($conn, 'GET', $path, $query);
    }

    /** POST to a Graph endpoint (publishing delivery). */
    public function post(MetaConnection $conn, string $path, array $params = []): array
    {
        return $this->call($conn, 'POST', $path, $params);
    }

    private function call(MetaConnection $conn, string $method, string $path, array $query): array
    {
        $token = $this->tokens->get($conn);
        if ($token === null || $token === '') {
            throw new MetaAuthException('Meta connection has no token', null, null, 401);
        }

        $uri = ($this->config['api_version'] ?? 'v21.0') . '/' . ltrim($path, '/');
        $attempt = 0;

        while (true) {
            [$status, $body, $usage, $transient] = $this->attempt($method, $uri, $query, $token);
            if ($usage !== null) {
                $conn->rate_limit_pct = $usage;
            }

            $error = is_array($body) ? ($body['error'] ?? null) : null;
            $code = $error ? (int) ($error['code'] ?? 0) : 0;
            $throttled = $status === 429 || $transient || ($error && $this->isThrottle($code));

            // Retry throttles/transient failures with exponential backoff.
            if ($throttled && $attempt < count(self::BACKOFF)) {
                ($this->sleeper)(self::BACKOFF[$attempt]);
                $attempt++;
                continue;
            }

            if ($status >= 200 && $status < 300 && !$error) {
                $this->record($conn, $method, $uri, $status, true, null, $usage);
                return is_array($body) ? $body : [];
            }

            $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
            $message = (string) ($error['message'] ?? "Meta request failed (HTTP {$status})");
            $this->record($conn, $method, $uri, $status, false, ['code' => $code, 'subcode' => $subcode, 'message' => $message], $usage);

            if ($throttled) {
                throw new MetaThrottleException($message, $code, $subcode, $status);
            }
            if ($status === 401 || $code === 190) {
                throw new MetaAuthException($message, $code, $subcode, $status);
            }
            throw new MetaException($message, $code, $subcode, $status);
        }
    }

    /** @return array{0:int,1:mixed,2:?int,3:bool} [status, decodedBody, usagePct, transient] */
    private function attempt(string $method, string $uri, array $query, string $token): array
    {
        try {
            // GET params ride in the query; POST params go in the body (access_token stays on the query).
            $options = ['http_errors' => false];
            if ($method === 'GET') {
                $options['query'] = array_merge($query, ['access_token' => $token]);
            } else {
                $options['query'] = ['access_token' => $token];
                $options['form_params'] = $query;
            }
            $res = $this->http->request($method, $uri, $options);
            $body = json_decode((string) $res->getBody(), true);
            return [$res->getStatusCode(), $body, $this->parseUsage($res), false];
        } catch (ConnectException) {
            return [0, null, null, true]; // network blip → treat as transient, retry
        }
    }

    private function isThrottle(int $code): bool
    {
        return in_array($code, self::RATE_LIMIT_CODES, true) || ($code >= 80000 && $code <= 80014);
    }

    /** Highest usage percentage across Meta's rate-limit headers, or null. */
    private function parseUsage(ResponseInterface $res): ?int
    {
        $values = [];

        $app = $res->getHeaderLine('X-App-Usage');
        if ($app !== '' && is_array($j = json_decode($app, true))) {
            foreach (['call_count', 'total_cputime', 'total_time'] as $k) {
                if (isset($j[$k])) {
                    $values[] = (int) $j[$k];
                }
            }
        }

        $buc = $res->getHeaderLine('X-Business-Use-Case-Usage');
        if ($buc !== '' && is_array($j = json_decode($buc, true))) {
            foreach ($j as $entries) {
                foreach ((array) $entries as $obj) {
                    foreach (['call_count', 'total_cputime', 'total_time'] as $k) {
                        if (isset($obj[$k])) {
                            $values[] = (int) $obj[$k];
                        }
                    }
                }
            }
        }

        return $values === [] ? null : max($values);
    }

    private function record(MetaConnection $conn, string $method, string $uri, int $status, bool $ok, ?array $err, ?int $usage): void
    {
        MetaApiLog::create([
            'meta_connection_id' => $conn->id,
            'client_email'       => $conn->client_email,
            'endpoint'           => $uri,
            'method'             => $method,
            'http_status'        => $status ?: null,
            'ok'                 => $ok,
            'error_code'         => $err['code'] ?? null,
            'error_subcode'      => $err['subcode'] ?? null,
            'error_message'      => $err['message'] ?? null,
            'usage_pct'          => $usage,
        ]);

        if ($ok) {
            $conn->last_success_at = date('Y-m-d H:i:s');
            $conn->status = 'connected';
        } else {
            $conn->last_error = $err['message'] ?? null;
            $conn->last_error_at = date('Y-m-d H:i:s');
            $conn->status = ($status === 401 || ($err['code'] ?? 0) === 190) ? 'expired' : 'error';
        }
        $conn->save();
    }
}
