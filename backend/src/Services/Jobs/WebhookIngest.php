<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Domain\Models\WebhookEvent;
use Illuminate\Database\QueryException;

/**
 * Inbound-webhook resilience pattern (Master Spec §17.1):
 *   verify signature (by the caller) → write raw event → dedupe on (provider,
 *   event_id) → enqueue async processing → let the endpoint return 200 fast.
 *
 * Signature verification is provider-specific, so the caller passes the result
 * ($verified) and this helper owns the durable/idempotent part.
 */
final class WebhookIngest
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    /**
     * @return array{status:'invalid'|'accepted'|'duplicate', event?:WebhookEvent}
     */
    public function ingest(
        string $provider,
        string $eventId,
        array $payload,
        bool $verified,
        string $jobName,
        ?string $clientEmail = null,
    ): array {
        if (!$verified) {
            return ['status' => 'invalid'];
        }

        // No id from the provider → can't dedupe; synthesize one so the row is still logged.
        if ($eventId === '') {
            $eventId = 'gen_' . bin2hex(random_bytes(8));
        }

        $existing = WebhookEvent::where('provider', $provider)->where('event_id', $eventId)->first();
        if ($existing) {
            return ['status' => 'duplicate', 'event' => $existing];
        }

        try {
            $event = WebhookEvent::create([
                'provider'    => $provider,
                'event_id'    => $eventId,
                'payload'     => $payload,
                'status'      => 'received',
                'received_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (QueryException $e) {
            // Lost a race on the UNIQUE(provider,event_id) index → treat as duplicate.
            $existing = WebhookEvent::where('provider', $provider)->where('event_id', $eventId)->first();
            if ($existing) {
                return ['status' => 'duplicate', 'event' => $existing];
            }
            throw $e;
        }

        $this->queue->enqueue($jobName, ['webhook_event_id' => $event->id], 0, $clientEmail, 'webhooks');

        return ['status' => 'accepted', 'event' => $event];
    }
}
