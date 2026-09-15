<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Models\ProjectQuote;

/**
 * Credential-free Stripe scaffold. Generates deterministic-looking session/intent
 * ids and a simulated Checkout URL so the accept → deposit → webhook → activate
 * flow is demonstrable and testable end-to-end WITHOUT real charges.
 *
 * The real StripeGateway (Checkout Session with automatic_tax for GST, metadata
 * {project_id, quote_id}, success/cancel URLs) drops in via DI with keys.
 */
final class MockStripeGateway implements PaymentGateway
{
    public function __construct(private readonly array $stripeConfig = [])
    {
    }

    public function createDepositCheckout(ProjectQuote $quote): array
    {
        $id = 'cs_test_' . bin2hex(random_bytes(12));
        $base = (string) ($this->stripeConfig['success_url'] ?? 'http://localhost:8080/projects');
        // A simulated hosted-checkout URL; real Stripe returns session->url.
        $sep = str_contains($base, '?') ? '&' : '?';
        $url = $base . $sep . 'simulated_checkout=' . $id . '&project_id=' . $quote->project_id . '&quote_id=' . $quote->id;

        return ['id' => $id, 'url' => $url];
    }

    public function createBalanceInvoice(ProjectQuote $quote, float $balance): array
    {
        return ['id' => 'in_test_' . bin2hex(random_bytes(12)), 'amount' => round($balance, 2)];
    }
}
