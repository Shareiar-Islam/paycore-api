<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\CheckoutTokenService;
use App\Support\MerchantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutTokenController extends Controller
{
    public function __construct(private CheckoutTokenService $checkoutTokenService)
    {
    }

    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:one_time,subscription'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $merchant = app(MerchantContext::class)->get();
        $token = $this->checkoutTokenService->issue($merchant, $data['purpose'], $data['ttl_minutes'] ?? 10);

        return response()->json([
            'token' => $token,
            'purpose' => $data['purpose'],
            'expires_in_minutes' => $data['ttl_minutes'] ?? 10,
        ], 201);
    }
}
