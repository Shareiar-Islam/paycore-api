<?php

namespace App\Services\Payments\Gateways;

use App\Models\Merchant;
use App\Services\Payments\Contracts\GatewayInterface;
use App\Services\Payments\MerchantCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements GatewayInterface
{
    public function __construct(private MerchantCredentialService $credentialService)
    {
    }

    public function createPayment(Merchant $merchant, array $payload): array
    {
        $client = $this->clientFor($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key');

        $session = $client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $payload['success_url'],
            'cancel_url' => $payload['cancel_url'],
            'customer_email' => Arr::get($payload, 'customer_email'),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payload['currency']),
                    'unit_amount' => (int) $payload['amount'],
                    'product_data' => [
                        'name' => Arr::get($payload, 'description', 'Payment'),
                    ],
                ],
            ]],
            'metadata' => Arr::get($payload, 'metadata', []),
        ], $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []);

        return [
            'id' => $session->id,
            'status' => $session->status,
            'url' => $session->url,
            'provider' => 'stripe',
            'raw' => $session->toArray(),
        ];
    }

    public function createSubscription(Merchant $merchant, array $payload): array
    {
        $client = $this->clientFor($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key');

        $session = $client->checkout->sessions->create([
            'mode' => 'subscription',
            'success_url' => $payload['success_url'],
            'cancel_url' => $payload['cancel_url'],
            'customer_email' => Arr::get($payload, 'customer_email'),
            'line_items' => [[
                'quantity' => 1,
                'price' => $payload['price_id'],
            ]],
            'metadata' => Arr::get($payload, 'metadata', []),
        ], $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []);

        return [
            'id' => $session->id,
            'status' => $session->status,
            'url' => $session->url,
            'provider' => 'stripe',
            'raw' => $session->toArray(),
        ];
    }

    public function refund(Merchant $merchant, array $payload): array
    {
        $client = $this->clientFor($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key');

        $refund = $client->refunds->create([
            'payment_intent' => $payload['payment_intent'],
            'amount' => Arr::get($payload, 'amount'),
            'reason' => Arr::get($payload, 'reason'),
            'metadata' => Arr::get($payload, 'metadata', []),
        ], $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []);

        return [
            'id' => $refund->id,
            'status' => $refund->status,
            'provider' => 'stripe',
            'raw' => $refund->toArray(),
        ];
    }

    public function verifyWebhook(Merchant $merchant, Request $request): array
    {
        $credentials = $this->credentialService->provider($merchant, 'stripe');
        $secret = $credentials['webhook_secret'] ?? null;

        if (! $secret) {
            throw new RuntimeException('Stripe webhook secret is missing.');
        }

        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $exception) {
            throw new RuntimeException('Invalid Stripe webhook signature.', previous: $exception);
        }

        return [
            'id' => $event->id ?? null,
            'type' => $event->type ?? null,
            'provider' => 'stripe',
            'payload' => json_decode($payload, true) ?: [],
        ];
    }

    private function clientFor(Merchant $merchant): StripeClient
    {
        $credentials = $this->credentialService->provider($merchant, 'stripe');
        $secret = $credentials['secret_key'] ?? null;

        if (! $secret) {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        return new StripeClient($secret);
    }
}
