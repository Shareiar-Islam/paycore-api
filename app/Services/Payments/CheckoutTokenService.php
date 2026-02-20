<?php

namespace App\Services\Payments;

use App\Models\CheckoutToken;
use App\Models\Merchant;
use RuntimeException;
use Illuminate\Support\Str;

class CheckoutTokenService
{
    public function issue(Merchant $merchant, string $purpose, int $ttlMinutes = 10): string
    {
        $plain = 'chk_'.Str::random(48);

        CheckoutToken::create([
            'merchant_id' => $merchant->id,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $plain;
    }

    public function consume(Merchant $merchant, string $plainToken, string $purpose): void
    {
        $token = CheckoutToken::query()
            ->where('merchant_id', $merchant->id)
            ->where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token || $token->used_at || $token->expires_at->isPast()) {
            throw new RuntimeException('Invalid or expired checkout token.');
        }

        $token->forceFill(['used_at' => now()])->save();
    }
}
