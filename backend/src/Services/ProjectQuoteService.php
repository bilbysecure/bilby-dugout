<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\ProjectStatus;
use App\Domain\Enums\QuoteStatus;
use App\Domain\Enums\Role;
use App\Domain\Models\Project;
use App\Domain\Models\ProjectQuote;
use App\Policies\AuthorizationException;
use App\Repositories\ProjectRepository;
use App\Services\Governance\ConsentService;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Quote lifecycle + commercial gate (Master Spec §10 / §11 Layer A).
 * Managers draft/send; the client owner accepts with a digital signature
 * (reusing the approval-signature pattern), which snapshots the terms.
 */
final class ProjectQuoteService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly PaymentGateway $payments,
        private readonly ActivityLogger $activity,
        private readonly ConsentService $consent,
    ) {
    }

    public function listForProject(Principal $p, int $projectId): array
    {
        $project = $this->projectOrFail($p, $projectId);
        return ProjectQuote::where('project_id', $project->id)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, int $projectId, array $data): ProjectQuote
    {
        $this->assertManager($p);
        $project = $this->projectOrFail($p, $projectId);

        $lineItems = $this->normalizeLineItems($data['line_items'] ?? []);
        $subtotal = $this->subtotal($lineItems, $data['subtotal'] ?? null);
        $depositPct = round((float) ($data['deposit_pct'] ?? 0), 2);
        $depositAmount = array_key_exists('deposit_amount', $data)
            ? round((float) $data['deposit_amount'], 2)
            : round($subtotal * $depositPct / 100, 2);

        $quote = ProjectQuote::create([
            'project_id'     => $project->id,
            'client_email'   => $project->client_email,
            'line_items'     => $lineItems,
            'subtotal'       => $this->money($subtotal),
            'deposit_pct'    => $this->money($depositPct),
            'deposit_amount' => $this->money($depositAmount),
            'currency'       => $data['currency'] ?? $project->currency ?? 'AUD',
            'terms'          => $data['terms'] ?? null,
            'valid_until'    => $data['valid_until'] ?? null,
            'status'         => QuoteStatus::Draft->value,
        ]);
        $this->activity->log($p, 'quote_created', null, null, $quote->status, 'quote', ['quote_id' => $quote->id, 'project_id' => $project->id]);
        return $quote;
    }

    public function send(Principal $p, int $quoteId): ProjectQuote
    {
        $this->assertManager($p);
        [$quote, $project] = $this->quoteOrFail($p, $quoteId);
        $this->transition($quote, QuoteStatus::Sent);

        // Project moves to "quoted" once the client has something to act on.
        if ($project->status === ProjectStatus::Draft->value) {
            $project->status = ProjectStatus::Quoted->value;
            $project->save();
        }
        $this->activity->log($p, 'quote_sent', null, null, $quote->status, 'quote', ['quote_id' => $quote->id]);
        return $quote;
    }

    /** Client-owner acceptance with a digital signature → snapshot terms. */
    public function accept(Principal $p, int $quoteId, array $data): ProjectQuote
    {
        [$quote, $project] = $this->quoteOrFail($p, $quoteId);
        $this->assertClientOwner($p, $project);

        if ($this->isExpired($quote)) {
            $quote->status = QuoteStatus::Expired->value;
            $quote->save();
            throw new InvalidArgumentException('This quote has expired');
        }

        $signature = trim((string) ($data['digital_signature'] ?? ''));
        if ($signature === '') {
            throw new InvalidArgumentException('digital_signature is required');
        }

        return DB::connection()->transaction(function () use ($p, $quote, $signature) {
            $this->transition($quote, QuoteStatus::Accepted);
            $quote->accepted_by = $p->email;
            $quote->accepted_signature = $signature;
            $quote->accepted_at = date('Y-m-d H:i:s');
            $quote->terms_snapshot = [
                'line_items'     => $quote->line_items,
                'subtotal'       => $quote->subtotal,
                'deposit_pct'    => $quote->deposit_pct,
                'deposit_amount' => $quote->deposit_amount,
                'currency'       => $quote->currency,
                'terms'          => $quote->terms,
                'valid_until'    => $quote->valid_until,
                'accepted_at'    => $quote->accepted_at,
            ];
            $quote->save();

            // Capture consent at quote acceptance (Master Spec §17.4).
            $this->consent->record($p->email, (string) $quote->client_email, 'quote_acceptance');

            $this->activity->log($p, 'quote_accepted', null, null, $quote->status, 'quote', ['quote_id' => $quote->id]);
            return $quote;
        });
    }

    public function decline(Principal $p, int $quoteId): ProjectQuote
    {
        [$quote, $project] = $this->quoteOrFail($p, $quoteId);
        // Either the owning client or a manager may decline.
        if (!$p->isManager() && !($p->role === Role::ClientOwner && $project->client_email === $p->clientEmail)) {
            throw new AuthorizationException('Not allowed to decline this quote');
        }
        $this->transition($quote, QuoteStatus::Declined);
        $this->activity->log($p, 'quote_declined', null, null, $quote->status, 'quote', ['quote_id' => $quote->id]);
        return $quote;
    }

    /** Client-owner starts the deposit Checkout for an accepted quote. */
    public function depositCheckout(Principal $p, int $quoteId): array
    {
        [$quote, $project] = $this->quoteOrFail($p, $quoteId);
        $this->assertClientOwner($p, $project);
        if ($quote->status !== QuoteStatus::Accepted->value) {
            throw new InvalidArgumentException('The quote must be accepted before paying the deposit');
        }

        $session = $this->payments->createDepositCheckout($quote);
        $quote->stripe_deposit_intent_id = $session['id'];
        $quote->save();

        return ['url' => $session['url'], 'session_id' => $session['id']];
    }

    // ── internals ────────────────────────────────────────────────

    private function transition(ProjectQuote $quote, QuoteStatus $to): void
    {
        $from = QuoteStatus::from($quote->status);
        if (!$from->canTransitionTo($to)) {
            throw new InvalidArgumentException("Cannot move quote from {$from->value} to {$to->value}");
        }
        $quote->status = $to->value;
        if ($to !== QuoteStatus::Accepted) {
            $quote->save();
        }
    }

    private function isExpired(ProjectQuote $quote): bool
    {
        return $quote->valid_until && strtotime((string) $quote->valid_until) < strtotime(date('Y-m-d'));
    }

    private function normalizeLineItems(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            $out[] = [
                'description' => (string) ($item['description'] ?? ''),
                'qty'         => (float) ($item['qty'] ?? 1),
                'unit_price'  => round((float) ($item['unit_price'] ?? 0), 2),
            ];
        }
        return $out;
    }

    /** Money as a fixed-2 string, so Eloquent's decimal cast never sees a float. */
    private function money(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    private function subtotal(array $lineItems, mixed $override): float
    {
        if ($override !== null && $override !== '') {
            return round((float) $override, 2);
        }
        $sum = 0.0;
        foreach ($lineItems as $i) {
            $sum += (float) $i['qty'] * (float) $i['unit_price'];
        }
        return round($sum, 2);
    }

    /** @return array{0:ProjectQuote,1:Project} */
    private function quoteOrFail(Principal $p, int $quoteId): array
    {
        $quote = ProjectQuote::find($quoteId);
        if (!$quote) {
            throw new ModelNotFoundException('Quote not found');
        }
        $project = $this->projects->find($p, (int) $quote->project_id); // tenant-scoped
        if (!$project) {
            throw new ModelNotFoundException('Quote not found');
        }
        return [$quote, $project];
    }

    private function projectOrFail(Principal $p, int $projectId): Project
    {
        $project = $this->projects->find($p, $projectId);
        if (!$project) {
            throw new ModelNotFoundException('Project not found');
        }
        return $project;
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }

    private function assertClientOwner(Principal $p, Project $project): void
    {
        if (!($p->role === Role::ClientOwner && $project->client_email === $p->clientEmail)) {
            throw new AuthorizationException('Only the client owner can perform this action');
        }
    }
}
