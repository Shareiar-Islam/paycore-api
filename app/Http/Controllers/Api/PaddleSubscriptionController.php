<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\Gateways\PaddleGateway;
use App\Support\MerchantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaddleSubscriptionController extends Controller
{
    public function __construct(private PaddleGateway $paddleGateway)
    {
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'string'],
            'immediate' => ['sometimes', 'boolean'],
        ]);

        $merchant = app(MerchantContext::class)->get();
        $response = $this->paddleGateway->cancelSubscription(
            $merchant,
            $data['subscription_id'],
            ! ($data['immediate'] ?? false)
        );

        return response()->json(['data' => $response]);
    }
}
