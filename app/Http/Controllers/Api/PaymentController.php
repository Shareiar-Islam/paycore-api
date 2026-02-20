<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Services\Payments\CheckoutTokenService;
use App\Services\Payments\PaymentManager;
use App\Support\MerchantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentManager $paymentManager,
        private CheckoutTokenService $checkoutTokenService
    ) {
    }

    public function createOneTime(Request $request, string $provider): JsonResponse
    {
        $this->assertSupportedProvider($provider);
        $merchant = app(MerchantContext::class)->get();
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
            'customer_email' => ['nullable', 'email'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'checkout_token' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyKey = $data['idempotency_key'] ?? (string) $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $existing = Payment::query()
                ->where('merchant_id', $merchant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json(['data' => $existing, 'idempotent' => true]);
            }
        }

        $attempt = PaymentAttempt::create([
            'merchant_id' => $merchant->id,
            'provider' => strtolower($provider),
            'action' => 'one_time',
            'status' => 'initiated',
            'idempotency_key' => $idempotencyKey,
            'request_payload' => collect($data)->except('checkout_token')->all(),
        ]);

        try {
            $this->checkoutTokenService->consume($merchant, $data['checkout_token'], 'one_time');

            $response = $this->paymentManager->gateway($provider)->createPayment($merchant, [
                ...$data,
                'idempotency_key' => $idempotencyKey,
            ]);

            $payment = Payment::create([
                'merchant_id' => $merchant->id,
                'provider' => strtolower($provider),
                'type' => 'one_time',
                'status' => $response['status'] ?? 'created',
                'external_id' => $response['id'] ?? null,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency']),
                'customer_email' => $data['customer_email'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $attempt->forceFill([
                'status' => 'succeeded',
                'external_reference' => $response['id'] ?? null,
                'response_payload' => $response,
            ])->save();

            return response()->json([
                'data' => $payment,
                'checkout_url' => $response['url'] ?? null,
            ], 201);
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function createSubscription(Request $request, string $provider): JsonResponse
    {
        $this->assertSupportedProvider($provider);
        $merchant = app(MerchantContext::class)->get();
        $common = [
            'checkout_token' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];

        $rules = strtolower($provider) === 'stripe'
            ? [
                ...$common,
                'price_id' => ['required', 'string'],
                'success_url' => ['required', 'url'],
                'cancel_url' => ['required', 'url'],
                'customer_email' => ['nullable', 'email'],
            ]
            : [
                ...$common,
                'price_id' => ['required', 'string'],
                'customer_id' => ['required', 'string'],
            ];

        $data = $request->validate($rules);
        $idempotencyKey = $data['idempotency_key'] ?? (string) $request->header('Idempotency-Key');

        $attempt = PaymentAttempt::create([
            'merchant_id' => $merchant->id,
            'provider' => strtolower($provider),
            'action' => 'subscription',
            'status' => 'initiated',
            'idempotency_key' => $idempotencyKey,
            'request_payload' => collect($data)->except('checkout_token')->all(),
        ]);

        try {
            $this->checkoutTokenService->consume($merchant, $data['checkout_token'], 'subscription');
            $response = $this->paymentManager->gateway($provider)->createSubscription($merchant, [
                ...$data,
                'idempotency_key' => $idempotencyKey,
            ]);

            $payment = Payment::create([
                'merchant_id' => $merchant->id,
                'provider' => strtolower($provider),
                'type' => 'subscription',
                'status' => $response['status'] ?? 'created',
                'external_id' => $response['id'] ?? null,
                'currency' => null,
                'amount' => null,
                'customer_email' => $data['customer_email'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $attempt->forceFill([
                'status' => 'succeeded',
                'external_reference' => $response['id'] ?? null,
                'response_payload' => $response,
            ])->save();

            return response()->json(['data' => $payment, 'provider_payload' => $response], 201);
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function refund(Request $request, string $provider): JsonResponse
    {
        $this->assertSupportedProvider($provider);
        $merchant = app(MerchantContext::class)->get();
        $data = $request->validate([
            'payment_id' => ['required', 'integer'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'payment_intent' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (strtolower($provider) === 'stripe' && empty($data['payment_intent'])) {
            return response()->json(['message' => 'payment_intent is required for Stripe refunds.'], 422);
        }

        if (strtolower($provider) === 'paddle' && empty($data['transaction_id'])) {
            return response()->json(['message' => 'transaction_id is required for Paddle refunds.'], 422);
        }

        $payment = Payment::query()
            ->where('merchant_id', $merchant->id)
            ->where('id', $data['payment_id'])
            ->firstOrFail();

        $idempotencyKey = $data['idempotency_key'] ?? (string) $request->header('Idempotency-Key');

        $attempt = PaymentAttempt::create([
            'merchant_id' => $merchant->id,
            'provider' => strtolower($provider),
            'action' => 'refund',
            'status' => 'initiated',
            'idempotency_key' => $idempotencyKey,
            'request_payload' => $data,
        ]);

        try {
            $response = $this->paymentManager->gateway($provider)->refund($merchant, [
                ...$data,
                'idempotency_key' => $idempotencyKey,
            ]);

            $refund = Refund::create([
                'merchant_id' => $merchant->id,
                'payment_id' => $payment->id,
                'provider' => strtolower($provider),
                'status' => $response['status'] ?? 'submitted',
                'external_refund_id' => $response['id'] ?? null,
                'amount' => $data['amount'] ?? null,
                'reason' => $data['reason'] ?? null,
                'metadata' => $response,
            ]);

            $attempt->forceFill([
                'status' => 'succeeded',
                'external_reference' => $response['id'] ?? null,
                'response_payload' => $response,
            ])->save();

            return response()->json(['data' => $refund], 201);
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function assertSupportedProvider(string $provider): void
    {
        abort_unless(in_array(strtolower($provider), ['stripe', 'paddle'], true), 404, 'Unsupported payment provider.');
    }
}
