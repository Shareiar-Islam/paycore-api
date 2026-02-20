<?php

namespace App\Services\Payments\Gateways;

use App\Models\Merchant;
use App\Services\Payments\Contracts\GatewayInterface;
use App\Services\Payments\MerchantCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaddleGateway implements GatewayInterface
{
    public function __construct(private MerchantCredentialService $credentialService)
    {
    }

    public function createPayment(Merchant $merchant, array $payload): array
    {
        $credentials = $this->credentials($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key', (string) Str::uuid());

        $response = Http::withToken($credentials['api_key'])
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl($credentials).'/transactions', [
                'items' => [[
                    'price' => [
                        'name' => Arr::get($payload, 'description', 'Payment'),
                        'unit_price' => [
                            'amount' => (string) ((int) $payload['amount']),
                            'currency_code' => strtoupper($payload['currency']),
                        ],
                        'quantity' => 1,
                    ],
                ]],
                'customer_email' => Arr::get($payload, 'customer_email'),
                'custom_data' => Arr::get($payload, 'metadata', []),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Paddle payment creation failed: '.$response->body());
        }

        $data = $response->json('data');

        return [
            'id' => Arr::get($data, 'id'),
            'status' => Arr::get($data, 'status'),
            'provider' => 'paddle',
            'url' => Arr::get($data, 'checkout.url'),
            'raw' => $response->json(),
        ];
    }

    public function createSubscription(Merchant $merchant, array $payload): array
    {
        $credentials = $this->credentials($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key', (string) Str::uuid());

        $response = Http::withToken($credentials['api_key'])
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl($credentials).'/subscriptions', [
                'items' => [[
                    'price_id' => $payload['price_id'],
                    'quantity' => 1,
                ]],
                'customer_id' => $payload['customer_id'],
                'custom_data' => Arr::get($payload, 'metadata', []),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Paddle subscription creation failed: '.$response->body());
        }

        $data = $response->json('data');

        return [
            'id' => Arr::get($data, 'id'),
            'status' => Arr::get($data, 'status'),
            'provider' => 'paddle',
            'raw' => $response->json(),
        ];
    }

    public function cancelSubscription(Merchant $merchant, string $subscriptionId, bool $effectiveFromNextBillingPeriod = true): array
    {
        $credentials = $this->credentials($merchant);

        $response = Http::withToken($credentials['api_key'])
            ->post($this->baseUrl($credentials)."/subscriptions/{$subscriptionId}/cancel", [
                'effective_from' => $effectiveFromNextBillingPeriod ? 'next_billing_period' : 'immediately',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Paddle cancellation failed: '.$response->body());
        }

        return [
            'status' => 'canceled',
            'provider' => 'paddle',
            'raw' => $response->json(),
        ];
    }

    public function refund(Merchant $merchant, array $payload): array
    {
        $credentials = $this->credentials($merchant);
        $idempotencyKey = Arr::get($payload, 'idempotency_key', (string) Str::uuid());

        $response = Http::withToken($credentials['api_key'])
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($this->baseUrl($credentials).'/adjustments', [
                'action' => 'refund',
                'transaction_id' => $payload['transaction_id'],
                'items' => Arr::get($payload, 'items', []),
                'reason' => Arr::get($payload, 'reason'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Paddle refund failed: '.$response->body());
        }

        $data = $response->json('data');

        return [
            'id' => Arr::get($data, 'id'),
            'status' => Arr::get($data, 'status'),
            'provider' => 'paddle',
            'raw' => $response->json(),
        ];
    }

    public function verifyWebhook(Merchant $merchant, Request $request): array
    {
        $credentials = $this->credentials($merchant);
        $secret = $credentials['webhook_secret'] ?? null;
        $signature = (string) $request->header('Paddle-Signature');

        if (! $secret || ! $this->verifySignature($request->getContent(), $signature, $secret)) {
            throw new RuntimeException('Invalid Paddle webhook signature.');
        }

        $payload = $request->json()->all();

        return [
            'id' => Arr::get($payload, 'event_id'),
            'type' => Arr::get($payload, 'event_type'),
            'provider' => 'paddle',
            'payload' => $payload,
        ];
    }

    private function credentials(Merchant $merchant): array
    {
        $credentials = $this->credentialService->provider($merchant, 'paddle');
        $this->configureCashier($credentials);

        if (! isset($credentials['api_key'])) {
            throw new RuntimeException('Paddle API key is missing.');
        }

        return $credentials;
    }

    private function configureCashier(array $credentials): void
    {
        config([
            'cashier.paddle.api_key' => $credentials['api_key'] ?? null,
            'cashier.paddle.vendor_id' => $credentials['vendor_id'] ?? null,
            'cashier.paddle.webhook_secret' => $credentials['webhook_secret'] ?? null,
            'cashier.paddle.sandbox' => (bool) ($credentials['sandbox'] ?? false),
        ]);
    }

    private function baseUrl(array $credentials): string
    {
        return ($credentials['sandbox'] ?? false)
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';
    }

    private function verifySignature(string $body, string $header, string $secret): bool
    {
        if ($header === '') {
            return false;
        }

        $pairs = collect(explode(';', $header))
            ->mapWithKeys(function (string $pair): array {
                [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $pairs->get('ts');
        $signature = $pairs->get('h1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $computed = hash_hmac('sha256', "{$timestamp}:{$body}", $secret);

        return hash_equals($computed, $signature);
    }
}
