<?php

namespace App\Support;

use App\Models\Merchant;

class MerchantContext
{
    private ?Merchant $merchant = null;

    public function set(Merchant $merchant): void
    {
        $this->merchant = $merchant;
    }

    public function get(): ?Merchant
    {
        return $this->merchant;
    }
}
