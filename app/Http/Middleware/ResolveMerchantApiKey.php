<?php

namespace App\Http\Middleware;

use App\Models\MerchantApiKey;
use App\Support\MerchantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMerchantApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->header('X-Api-Key', '');

        if ($apiKey === '') {
            return response()->json(['message' => 'Missing X-Api-Key header.'], 401);
        }

        $prefix = substr($apiKey, 0, 12);

        $key = MerchantApiKey::query()
            ->with('merchant')
            ->where('key_prefix', $prefix)
            ->whereNull('revoked_at')
            ->first();

        if (! $key || ! hash_equals($key->key_hash, hash('sha256', $apiKey))) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if ($key->expires_at && $key->expires_at->isPast()) {
            return response()->json(['message' => 'Expired API key.'], 401);
        }

        app(MerchantContext::class)->set($key->merchant);
        $key->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
