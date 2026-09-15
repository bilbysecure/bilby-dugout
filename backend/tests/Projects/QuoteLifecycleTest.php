<?php

declare(strict_types=1);

namespace Tests\Projects;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Project;
use App\Domain\Models\ProjectQuote;
use App\Repositories\ProjectRepository;
use App\Services\ActivityLogger;
use App\Services\Governance\ConsentService;
use App\Services\Payments\MockStripeGateway;
use App\Services\ProjectQuoteService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuoteLifecycleTest extends TestCase
{
    private ProjectQuoteService $svc;

    protected function setUp(): void
    {
        ProjectQuote::query()->delete();
        Project::query()->delete();
        $this->svc = new ProjectQuoteService(new ProjectRepository(), new MockStripeGateway([]), new ActivityLogger(), new ConsentService(['privacy_version' => 'test', 'terms_version' => 'test']));
    }

    private function manager(): Principal
    {
        return new Principal(userId: 1, email: 'admin@bilbypixel.com', name: 'Ava', role: Role::GlobalAdmin);
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 2, email: $email, name: 'Olivia', role: Role::ClientOwner, clientEmail: $email);
    }

    private function project(string $client = 'owner@acme.com'): Project
    {
        return Project::create(['client_email' => $client, 'title' => 'Campaign', 'type' => 'ad_campaign', 'status' => 'draft', 'currency' => 'AUD']);
    }

    public function test_create_computes_subtotal_and_deposit(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, [
            'line_items' => [['description' => 'Ads', 'qty' => 2, 'unit_price' => 500], ['description' => 'Design', 'qty' => 1, 'unit_price' => 1000]],
            'deposit_pct' => 30,
        ]);

        self::assertSame('draft', $quote->status);
        self::assertSame('2000.00', (string) $quote->subtotal);
        self::assertSame('600.00', (string) $quote->deposit_amount);
    }

    public function test_happy_path_draft_to_sent_to_accepted_snapshots_terms(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]], 'deposit_pct' => 50, 'terms' => 'Net 7']);

        $this->svc->send($this->manager(), $quote->id);
        self::assertSame('sent', $quote->fresh()->status);
        self::assertSame('quoted', $project->fresh()->status);

        $accepted = $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'Olivia Owner']);
        self::assertSame('accepted', $accepted->status);
        self::assertSame('owner@acme.com', $accepted->accepted_by);
        self::assertSame('Olivia Owner', $accepted->accepted_signature);
        self::assertNotNull($accepted->accepted_at);
        self::assertSame('Net 7', $accepted->terms_snapshot['terms'], 'terms frozen at acceptance');
    }

    public function test_cannot_accept_a_draft_quote(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]]]);

        $this->expectExceptionMessage('Cannot move quote from draft to accepted');
        $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'sig']);
    }

    public function test_cannot_reaccept_a_terminal_quote(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]]]);
        $this->svc->send($this->manager(), $quote->id);
        $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'sig']);

        $this->expectException(InvalidArgumentException::class);
        $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'sig again']);
    }

    public function test_accept_requires_signature(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]]]);
        $this->svc->send($this->manager(), $quote->id);

        $this->expectExceptionMessage('digital_signature is required');
        $this->svc->accept($this->owner(), $quote->id, []);
    }

    public function test_expired_quote_cannot_be_accepted(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]], 'valid_until' => date('Y-m-d', strtotime('-1 day'))]);
        $this->svc->send($this->manager(), $quote->id);

        $this->expectExceptionMessage('expired');
        $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'sig']);
    }

    public function test_deposit_checkout_requires_accepted_and_records_session(): void
    {
        $project = $this->project();
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]], 'deposit_pct' => 50]);
        $this->svc->send($this->manager(), $quote->id);
        $this->svc->accept($this->owner(), $quote->id, ['digital_signature' => 'sig']);

        $checkout = $this->svc->depositCheckout($this->owner(), $quote->id);
        self::assertStringStartsWith('cs_test_', $checkout['session_id']);
        self::assertSame($checkout['session_id'], $quote->fresh()->stripe_deposit_intent_id);
    }

    public function test_cross_tenant_owner_cannot_accept(): void
    {
        $project = $this->project('owner@acme.com');
        $quote = $this->svc->create($this->manager(), $project->id, ['line_items' => [['description' => 'x', 'qty' => 1, 'unit_price' => 100]]]);
        $this->svc->send($this->manager(), $quote->id);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->svc->accept($this->owner('stranger@other.com'), $quote->id, ['digital_signature' => 'sig']);
    }
}
