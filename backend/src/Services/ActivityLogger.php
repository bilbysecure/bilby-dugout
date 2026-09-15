<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\ActivityLog;
use App\Domain\Models\Request;

/** Writes immutable audit-trail rows (powers the Request audit trail / team activity log). */
final class ActivityLogger
{
    /** Keys that must never be persisted to the audit trail (Master Spec §17.4). */
    private const SENSITIVE = [
        'password', 'password_hash', 'secret', 'token', 'access_token', 'refresh_token',
        'jti', 'signature', 'digital_signature', 'accepted_signature',
        'stripe_customer_id', 'stripe_deposit_intent_id', 'client_secret', 'api_key',
    ];

    public function log(
        Principal $actor,
        string $action,
        ?Request $request = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        string $entityType = 'request',
        array $metadata = [],
    ): void {
        ActivityLog::create([
            'request_id'  => $request?->id,
            'actor_email' => $actor->email,
            'actor_name'  => $actor->name,
            'action'      => $action,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'entity_type' => $entityType,
            'metadata'    => $this->sanitize($metadata) ?: null,
        ]);
    }

    /** Strip sensitive keys (recursively) so they never reach the audit trail. */
    private function sanitize(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE, true)) {
                $metadata[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->sanitize($value);
            }
        }
        return $metadata;
    }
}
