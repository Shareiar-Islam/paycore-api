<?php

namespace App\Http\Middleware;

use App\Support\MerchantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantScopedModel
{
    public function handle(Request $request, Closure $next): Response
    {
        $merchant = app(MerchantContext::class)->get();

        if (! $merchant) {
            return response()->json(['message' => 'Merchant context missing.'], 403);
        }

        foreach ($request->route()->parameters() as $parameter) {
            if (is_object($parameter) && method_exists($parameter, 'getAttribute')) {
                $resourceMerchantId = $parameter->getAttribute('merchant_id');

                if ($resourceMerchantId !== null && (int) $resourceMerchantId !== (int) $merchant->id) {
                    return response()->json(['message' => 'Forbidden merchant resource.'], 403);
                }
            }
        }

        return $next($request);
    }
}
