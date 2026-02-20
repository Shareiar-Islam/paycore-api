<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutTokenController;
use App\Http\Controllers\Api\MerchantCredentialController;
use App\Http\Controllers\Api\PaddleSubscriptionController;
use App\Http\Controllers\Api\PaddleWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('merchant.auth');
});

Route::middleware('merchant.auth')->group(function (): void {
    Route::post('/credentials/stripe', [MerchantCredentialController::class, 'upsertStripe']);
    Route::post('/credentials/paddle', [MerchantCredentialController::class, 'upsertPaddle']);

    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{key}', [ApiKeyController::class, 'revoke'])->middleware('merchant.scope');

    Route::post('/checkout-tokens', [CheckoutTokenController::class, 'issue']);
});

Route::middleware('merchant.api_key')->group(function (): void {
    Route::post('/payments/{provider}/one-time', [PaymentController::class, 'createOneTime']);
    Route::post('/payments/{provider}/subscriptions', [PaymentController::class, 'createSubscription']);
    Route::post('/payments/{provider}/refunds', [PaymentController::class, 'refund']);
    Route::post('/paddle/subscriptions/cancel', [PaddleSubscriptionController::class, 'cancel']);
});

Route::post('/webhooks/stripe/{merchant}', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/paddle/{merchant}', [PaddleWebhookController::class, 'handle']);
