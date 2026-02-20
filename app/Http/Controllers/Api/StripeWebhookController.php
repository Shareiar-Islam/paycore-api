<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __construct(private PaymentManager $paymentManager)
    {
    }

    public function handle(Request $request, Merchant $merchant): JsonResponse
    {
        try {
            $verified = $this->paymentManager->gateway('stripe')->verifyWebhook($merchant, $request);

            $event = WebhookEvent::create([
                'merchant_id' => $merchant->id,
                'provider' => 'stripe',
                'event_id' => $verified['id'] ?? null,
                'event_type' => $verified['type'] ?? null,
                'signature_valid' => true,
                'payload' => $verified['payload'] ?? [],
                'processed_at' => now(),
            ]);

            $this->applyPaymentStatusUpdates($merchant->id, $verified);

            return response()->json(['received' => true, 'event_id' => $event->id]);
        } catch (Throwable $exception) {
            WebhookEvent::create([
                'merchant_id' => $merchant->id,
                'provider' => 'stripe',
                'signature_valid' => false,
                'payload' => ['error' => $exception->getMessage()],
            ]);

            return response()->json(['message' => 'Webhook rejected.'], 400);
        }
    }

    private function applyPaymentStatusUpdates(int $merchantId, array $verified): void
    {
        $type = $verified['type'] ?? '';
        $object = data_get($verified, 'payload.data.object', []);
        $externalId = $object['id'] ?? null;

        if (! $externalId) {
            return;
        }

        $payment = \App\Models\Payment::query()
            ->where('merchant_id', $merchantId)
            ->where('external_id', $externalId)
            ->first();

        if (! $payment) {
            return;
        }

        if (in_array($type, ['checkout.session.completed', 'invoice.paid'], true)) {
            $payment->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        }

        if (in_array($type, ['checkout.session.expired', 'invoice.payment_failed'], true)) {
            $payment->forceFill(['status' => 'failed'])->save();
        }

        if ($type === 'charge.refunded') {
            $payment->forceFill(['status' => 'refunded'])->save();
        }
    }
}
