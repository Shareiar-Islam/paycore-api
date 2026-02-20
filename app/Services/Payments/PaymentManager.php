<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\GatewayInterface;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * @param  array<string, GatewayInterface>  $gateways
     */
    public function __construct(private array $gateways)
    {
    }

    public function gateway(string $provider): GatewayInterface
    {
        $provider = strtolower($provider);

        if (! isset($this->gateways[$provider])) {
            throw new InvalidArgumentException("Unsupported gateway [{$provider}].");
        }

        return $this->gateways[$provider];
    }
}
