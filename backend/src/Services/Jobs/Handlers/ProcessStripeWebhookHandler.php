<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\ProjectQuote;
use App\Domain\Models\WebhookEvent;
use App\Services\Jobs\JobHandler;
use App\Services\ProjectService;

/**
 * Async processor for a stored Stripe webhook. Wired via WebhookIngest so the
 * HTTP endpoint returns 200 immediately and this runs on the worker.
 *
 * Handles `checkout.session.completed` for the one-off deposit flow (Master Spec
 * §10): it flips the paid project to active. Subscription/invoice sync for other
 * event types remains deferred to the billing phase.
 */
final class ProcessStripeWebhookHandler implements JobHandler
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function handle(Job $job): void
    {
        $eventId = $job->payload['webhook_event_id'] ?? null;
        if ($eventId === null) {
            return;
        }

        /** @var WebhookEvent|null $event */
        $event = WebhookEvent::find($eventId);
        if (!$event) {
            return;
        }

        $event->status = 'processing';
        $event->save();

        $payload = (array) ($event->payload ?? []);
        if (($payload['type'] ?? null) === 'checkout.session.completed') {
            $this->activateProjectFromDeposit($payload);
        }
        // TODO(billing phase): customer.subscription.* / invoice.* sync.

        $event->status = 'processed';
        $event->processed_at = date('Y-m-d H:i:s');
        $event->save();
    }

    /** Resolve the project from the session metadata (or quote) and activate it. */
    private function activateProjectFromDeposit(array $payload): void
    {
        $session = (array) ($payload['data']['object'] ?? []);
        $metadata = (array) ($session['metadata'] ?? []);

        $projectId = isset($metadata['project_id']) ? (int) $metadata['project_id'] : 0;

        // Fallback: map the checkout session id back to the quote that owns it.
        if ($projectId === 0 && !empty($session['id'])) {
            $quote = ProjectQuote::where('stripe_deposit_intent_id', $session['id'])->first();
            $projectId = (int) ($quote->project_id ?? 0);
        }

        if ($projectId > 0) {
            $this->projects->activateFromDeposit($projectId);
        }
    }
}
