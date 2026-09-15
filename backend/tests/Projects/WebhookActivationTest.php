<?php

declare(strict_types=1);

namespace Tests\Projects;

use App\Domain\Models\Job;
use App\Domain\Models\Project;
use App\Domain\Models\ProjectQuote;
use App\Domain\Models\WebhookEvent;
use App\Repositories\ProjectRepository;
use App\Services\ActivityLogger;
use App\Services\Jobs\Handlers\ProcessStripeWebhookHandler;
use App\Services\Payments\MockStripeGateway;
use App\Services\ProjectService;
use PHPUnit\Framework\TestCase;

/** checkout.session.completed (mock Stripe) → project activated. */
final class WebhookActivationTest extends TestCase
{
    protected function setUp(): void
    {
        WebhookEvent::query()->delete();
        Project::query()->delete();
        ProjectQuote::query()->delete();
    }

    private function handler(): ProcessStripeWebhookHandler
    {
        $projects = new ProjectService(new ProjectRepository(), new MockStripeGateway([]), new ActivityLogger());
        return new ProcessStripeWebhookHandler($projects);
    }

    private function event(array $payload): WebhookEvent
    {
        return WebhookEvent::create([
            'provider' => 'stripe', 'event_id' => 'evt_' . uniqid(),
            'payload' => $payload, 'status' => 'received', 'received_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function job(int $eventId): Job
    {
        return new Job(['name' => 'stripe.webhook', 'payload' => ['webhook_event_id' => $eventId]]);
    }

    public function test_checkout_completed_activates_project_via_metadata(): void
    {
        $project = Project::create(['client_email' => 'owner@acme.com', 'title' => 'Ad', 'type' => 'ad_campaign', 'status' => 'quoted']);
        $event = $this->event([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_1', 'metadata' => ['project_id' => $project->id]]],
        ]);

        $this->handler()->handle($this->job($event->id));

        self::assertSame('active', $project->fresh()->status);
        self::assertSame('processed', $event->fresh()->status);
    }

    public function test_activation_falls_back_to_session_id_on_quote(): void
    {
        $project = Project::create(['client_email' => 'owner@acme.com', 'title' => 'Ad', 'type' => 'ad_campaign', 'status' => 'quoted']);
        ProjectQuote::create(['project_id' => $project->id, 'client_email' => 'owner@acme.com', 'status' => 'accepted', 'stripe_deposit_intent_id' => 'cs_test_fallback']);

        $event = $this->event([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_fallback', 'metadata' => []]],
        ]);
        $this->handler()->handle($this->job($event->id));

        self::assertSame('active', $project->fresh()->status);
    }

    public function test_activation_is_idempotent(): void
    {
        $project = Project::create(['client_email' => 'owner@acme.com', 'title' => 'Ad', 'type' => 'ad_campaign', 'status' => 'quoted']);
        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_x', 'metadata' => ['project_id' => $project->id]]]];

        $this->handler()->handle($this->job($this->event($payload)->id));
        $this->handler()->handle($this->job($this->event($payload)->id));

        self::assertSame('active', $project->fresh()->status);
    }

    public function test_unrelated_event_does_not_activate(): void
    {
        $project = Project::create(['client_email' => 'owner@acme.com', 'title' => 'Ad', 'type' => 'ad_campaign', 'status' => 'quoted']);
        $event = $this->event(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_1']]]);

        $this->handler()->handle($this->job($event->id));

        self::assertSame('quoted', $project->fresh()->status);
        self::assertSame('processed', $event->fresh()->status);
    }
}
