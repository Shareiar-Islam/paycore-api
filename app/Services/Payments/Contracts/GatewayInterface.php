<?php

namespace App\Services\Payments\Contracts;

use App\Models\Merchant;
use Illuminate\Http\Request;

interface GatewayInterface
{
    public function createPayment(Merchant $merchant, array $payload): array;

    public function createSubscription(Merchant $merchant, array $payload): array;

    public function refund(Merchant $merchant, array $payload): array;

    public function verifyWebhook(Merchant $merchant, Request $request): array;
}
