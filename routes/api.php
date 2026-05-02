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
Route::get('/', fn () => response()->json(['message' => 'welcome to the merchant api']));

Route::prefix('auth')->controller(AuthController::class)->group(function (): void {
    Route::post('/register', ['register']);
    Route::post('/login', ['login']);
    Route::post('/logout', ['logout'])->middleware('merchant.auth');
});

Route::middleware('merchant.auth')->controller(MerchantCredentialController::class)->group(function (): void {
    Route::post('/credentials/stripe', ['upsertStripe']);
    Route::post('/credentials/paddle', ['upsertPaddle']);
});

Route::middleware('merchant.auth')->controller(ApiKeyController::class)->group(function (): void {
    Route::get('/api-keys', ['index']);
    Route::post('/api-keys', ['store']);
    Route::delete('/api-keys/{key}', ['revoke'])->middleware('merchant.scope');

    Route::post('/checkout-tokens', ['issue']);
});

Route::middleware('merchant.api_key')->controller(PaymentController::class)->group(function (): void {
    Route::post('/payments/{provider}/one-time', ['createOneTime']);
    Route::post('/payments/{provider}/subscriptions', ['createSubscription']);
    Route::post('/payments/{provider}/refunds', ['refund']);
    Route::post('/paddle/subscriptions/cancel', ['cancel']);
});

Route::post('/webhooks/stripe/{merchant}', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/paddle/{merchant}', [PaddleWebhookController::class, 'handle']);
