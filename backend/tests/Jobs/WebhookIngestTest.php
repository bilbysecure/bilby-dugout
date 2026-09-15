<?php

declare(strict_types=1);

namespace Tests\Jobs;

use App\Domain\Models\Job;
use App\Domain\Models\WebhookEvent;
use App\Services\Jobs\QueueService;
use App\Services\Jobs\WebhookIngest;
use PHPUnit\Framework\TestCase;

final class WebhookIngestTest extends TestCase
{
    private WebhookIngest $ingest;

    protected function setUp(): void
    {
        WebhookEvent::query()->delete();
        Job::query()->delete();
        $this->ingest = new WebhookIngest(new QueueService());
    }

    public function test_accepts_stores_and_enqueues_processing(): void
    {
        $result = $this->ingest->ingest('stripe', 'evt_1', ['id' => 'evt_1', 'type' => 'invoice.paid'], true, 'stripe.webhook');

        self::assertSame('accepted', $result['status']);
        self::assertSame(1, WebhookEvent::count());
        self::assertSame('received', WebhookEvent::first()->status);
        self::assertSame(1, Job::where('name', 'stripe.webhook')->where('queue', 'webhooks')->count());
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        $payload = ['id' => 'evt_dup', 'type' => 'customer.subscription.updated'];
        $first = $this->ingest->ingest('stripe', 'evt_dup', $payload, true, 'stripe.webhook');
        $second = $this->ingest->ingest('stripe', 'evt_dup', $payload, true, 'stripe.webhook');

        self::assertSame('accepted', $first['status']);
        self::assertSame('duplicate', $second['status']);
        self::assertSame(1, WebhookEvent::count(), 'no duplicate row');
        self::assertSame(1, Job::count(), 'not re-enqueued');
    }

    public function test_same_event_id_different_provider_is_not_a_duplicate(): void
    {
        $this->ingest->ingest('stripe', 'evt_x', [], true, 'stripe.webhook');
        $result = $this->ingest->ingest('graph', 'evt_x', [], true, 'stripe.webhook');

        self::assertSame('accepted', $result['status']);
        self::assertSame(2, WebhookEvent::count());
    }

    public function test_invalid_signature_is_not_stored_or_enqueued(): void
    {
        $result = $this->ingest->ingest('stripe', 'evt_bad', ['id' => 'evt_bad'], false, 'stripe.webhook');

        self::assertSame('invalid', $result['status']);
        self::assertSame(0, WebhookEvent::count());
        self::assertSame(0, Job::count());
    }
}
