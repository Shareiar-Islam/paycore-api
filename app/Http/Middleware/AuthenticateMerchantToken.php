<?php

namespace App\Http\Middleware;

use App\Models\MerchantUserToken;
use App\Support\MerchantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMerchantToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();

        if (! $rawToken) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $token = MerchantUserToken::query()
            ->with(['user', 'merchant'])
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if (! $token || $token->revoked_at || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        Auth::setUser($token->user);
        app(MerchantContext::class)->set($token->merchant);
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
