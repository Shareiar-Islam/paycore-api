<?php

namespace App\Services\Payments;

use App\Models\Merchant;
use RuntimeException;

class MerchantCredentialService
{
    public function provider(Merchant $merchant, string $provider): array
    {
        $record = $merchant->providerCredentials()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();

        if (! $record) {
            throw new RuntimeException("Missing active {$provider} credentials.");
        }

        return $record->credentials ?? [];
    }
}
