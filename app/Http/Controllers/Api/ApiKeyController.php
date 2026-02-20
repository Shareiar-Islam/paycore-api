<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantApiKey;
use App\Support\MerchantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(): JsonResponse
    {
        $merchant = app(MerchantContext::class)->get();

        return response()->json([
            'data' => MerchantApiKey::query()
                ->where('merchant_id', $merchant->id)
                ->orderByDesc('id')
                ->get(['id', 'name', 'key_prefix', 'last_used_at', 'expires_at', 'revoked_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $merchant = app(MerchantContext::class)->get();
        $plain = 'pkm_'.Str::random(56);

        $apiKey = MerchantApiKey::create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null,
        ]);

        return response()->json([
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'api_key' => $plain,
            'expires_at' => $apiKey->expires_at,
        ], 201);
    }

    public function revoke(MerchantApiKey $key): JsonResponse
    {
        $key->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => 'API key revoked.']);
    }
}
