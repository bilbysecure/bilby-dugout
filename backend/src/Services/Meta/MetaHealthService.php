<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Auth\Principal;
use App\Domain\Models\MetaApiLog;
use App\Domain\Models\MetaConnection;
use App\Repositories\MetaConnectionRepository;

/**
 * Integration health (Master Spec §16) — surfaces connection status, last
 * successful call, rate-limit headroom and recent errors so silent data gaps are
 * visible.
 */
final class MetaHealthService
{
    public function __construct(private readonly MetaConnectionRepository $repo)
    {
    }

    public function health(Principal $p, ?string $clientEmail = null): array
    {
        $conn = $this->repo->forClient($p, $clientEmail);

        if (!$conn) {
            return [
                'status'    => 'disconnected',
                'connected' => false,
                'recent_errors' => [],
            ];
        }

        return [
            'status'              => $conn->status,
            'connected'           => $conn->isConnected(),
            'last_success_at'     => $conn->last_success_at,
            'last_error'          => $conn->last_error,
            'last_error_at'       => $conn->last_error_at,
            'rate_limit_pct'      => $conn->rate_limit_pct,
            'rate_limit_headroom' => $conn->rate_limit_pct === null ? null : max(0, 100 - (int) $conn->rate_limit_pct),
            'ad_account_ids'      => $conn->ad_account_ids,
            'scopes_granted'      => $conn->scopes_granted,
            'recent_errors'       => $this->recentErrors($conn),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function recentErrors(MetaConnection $conn): array
    {
        return MetaApiLog::where('client_email', $conn->client_email)
            ->where('ok', false)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['endpoint', 'http_status', 'error_code', 'error_message', 'created_at'])
            ->toArray();
    }
}
