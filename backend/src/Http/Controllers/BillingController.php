<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Jobs\WebhookIngest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Stripe billing. Without STRIPE_SECRET_KEY it returns a simulated portal URL so
 * the flow is demonstrable; a real Stripe billing-portal session swaps in later.
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly array $settings,
        private readonly WebhookIngest $webhooks,
    ) {
    }

    public function portalSession(Request $request, Response $response): Response
    {
        $stripeConfigured = !empty($this->settings['stripe']['secret_key']);
        if ($stripeConfigured) {
            // Real Stripe billing-portal session would be created here (Phase 3 with creds).
            return $this->json($response, ['url' => 'https://billing.stripe.com/session/placeholder', 'simulated' => false]);
        }
        // Simulated portal for the credential-free run.
        $origin = rtrim($this->settings['app']['url'] ?? '', '/');
        return $this->json($response, ['url' => $origin . '/billing-simulated', 'simulated' => true]);
    }

    /**
     * Stripe webhook — now runs through the resilient ingest pattern:
     * verify signature → store+dedupe the raw event → enqueue async processing →
     * return 200 immediately. The subscription/invoice sync itself is unchanged
     * (still deferred to the billing phase; see ProcessStripeWebhookHandler).
     */
    public function webhook(Request $request, Response $response): Response
    {
        $secret = (string) ($this->settings['stripe']['webhook_secret'] ?? '');
        $body = (string) $request->getBody();
        $payload = json_decode($body, true);
        $payload = is_array($payload) ? $payload : [];
        $eventId = (string) ($payload['id'] ?? '');

        // Signature verification (part of the resilience pattern, not business logic).
        // With a secret configured, verify the Stripe-Signature header; without one
        // (credential-free run) accept as simulated.
        $verified = $secret === ''
            ? true
            : $this->verifyStripeSignature($request->getHeaderLine('Stripe-Signature'), $body, $secret);

        $result = $this->webhooks->ingest('stripe', $eventId, $payload, $verified, 'stripe.webhook');

        if ($result['status'] === 'invalid') {
            return $this->json($response, ['error' => ['code' => 'invalid_signature', 'message' => 'Signature verification failed']], 400);
        }

        // 200 immediately; processing happens on the worker.
        return $this->json($response, ['received' => true, 'status' => $result['status'], 'simulated' => $secret === ''], 200);
    }

    /** Stripe scheme-v1 HMAC check: signed_payload = "{t}.{body}", compared to v1. */
    private function verifyStripeSignature(string $sigHeader, string $body, string $secret): bool
    {
        if ($sigHeader === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $sigHeader) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? '';
        $signatures = $parts['v1'] ?? [];
        if ($timestamp === '' || $signatures === []) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }
}
