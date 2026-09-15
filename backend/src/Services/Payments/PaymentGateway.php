<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Domain\Models\ProjectQuote;

/**
 * Payment provider contract (Master Spec §10). Scaffolds Stripe Checkout for the
 * one-off deposit and the completion balance invoice. The real Stripe driver is
 * swapped in via DI once keys are supplied — call sites never change.
 *
 * NOTE: no real charges are performed here; a live driver is credential-gated.
 */
interface PaymentGateway
{
    /**
     * Create a Checkout Session for a quote's deposit.
     *
     * @return array{id:string, url:string} session id + redirect URL
     */
    public function createDepositCheckout(ProjectQuote $quote): array;

    /**
     * Create a balance invoice for the remainder at project completion.
     *
     * @return array{id:string, amount:float} provider invoice id + amount
     */
    public function createBalanceInvoice(ProjectQuote $quote, float $balance): array;
}
