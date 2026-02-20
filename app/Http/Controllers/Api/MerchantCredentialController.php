<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantProviderCredential;
use App\Support\MerchantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantCredentialController extends Controller
{
    public function upsertStripe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'secret_key' => ['required', 'string'],
            'publishable_key' => ['required', 'string'],
            'webhook_secret' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->upsertProvider('stripe', $data);
    }

    public function upsertPaddle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_key' => ['required', 'string'],
            'vendor_id' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
            'sandbox' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->upsertProvider('paddle', $data);
    }

    private function upsertProvider(string $provider, array $credentials): JsonResponse
    {
        $merchant = app(MerchantContext::class)->get();

        $record = MerchantProviderCredential::updateOrCreate(
            [
                'merchant_id' => $merchant->id,
                'provider' => $provider,
            ],
            [
                'credentials' => collect($credentials)->except('is_active')->all(),
                'is_active' => $credentials['is_active'] ?? true,
            ]
        );

        return response()->json([
            'provider' => $record->provider,
            'is_active' => $record->is_active,
            'updated_at' => $record->updated_at,
        ]);
    }
}
