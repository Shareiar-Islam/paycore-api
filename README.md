<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About PayCore Backend

PayCore Backend is a Laravel-based multi-gateway payment processing API designed for merchants to integrate multiple payment providers seamlessly. It provides a unified API for handling payments, subscriptions, refunds, and webhooks across different payment processors.

## Features

- **Multi-Gateway Support**: Integrated with Stripe and Paddle payment providers
- **Merchant Management**: Create and manage multiple merchant accounts with separate credentials
- **API Key Authentication**: Secure API key-based authentication for merchant requests
- **Checkout Tokens**: Issue secure checkout tokens for payment sessions
- **One-Time Payments**: Process single payments through any supported gateway
- **Subscriptions**: Manage recurring subscriptions with Paddle
- **Refunds**: Process full and partial refunds
- **Webhook Handling**: Receive and process real-time events from payment providers

## Supported Payment Providers

- **Stripe**: One-time payments, refunds, webhook events
- **Paddle**: One-time payments, subscriptions, refunds, webhook events

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register a new merchant |
| POST | `/api/auth/login` | Login and obtain token |
| POST | `/api/auth/logout` | Logout current user |

### API Keys
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/api-keys` | List all API keys |
| POST | `/api/api-keys` | Create new API key |
| DELETE | `/api/api-keys/{key}` | Revoke API key |

### Credentials
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/credentials/stripe` | Save/update Stripe credentials |
| POST | `/api/credentials/paddle` | Save/update Paddle credentials |

### Checkout Tokens
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/checkout-tokens` | Issue a new checkout token |

### Payments
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/payments/{provider}/one-time` | Create one-time payment |
| POST | `/api/payments/{provider}/subscriptions` | Create subscription |
| POST | `/api/payments/{provider}/refunds` | Process refund |
| POST | `/api/paddle/subscriptions/cancel` | Cancel subscription |

### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/webhooks/stripe/{merchant}` | Handle Stripe webhooks |
| POST | `/api/webhooks/paddle/{merchant}` | Handle Paddle webhooks |

## Architecture

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── ApiKeyController.php
│   │   ├── AuthController.php
│   │   ├── CheckoutTokenController.php
│   │   ├── MerchantCredentialController.php
│   │   ├── PaddleSubscriptionController.php
│   │   ├── PaddleWebhookController.php
│   │   ├── PaymentController.php
│   │   └── StripeWebhookController.php
│   └── Middleware/
├── Models/
│   ├── CheckoutToken.php
│   ├── Merchant.php
│   ├── MerchantApiKey.php
│   ├── MerchantProviderCredential.php
│   ├── MerchantUserToken.php
│   ├── Payment.php
│   ├── PaymentAttempt.php
│   ├── Refund.php
│   ├── User.php
│   └── WebhookEvent.php
├── Services/Payments/
│   ├── Contracts/GatewayInterface.php
│   ├── Gateways/
│   │   ├── PaddleGateway.php
│   │   └── StripeGateway.php
│   ├── CheckoutTokenService.php
│   ├── MerchantCredentialService.php
│   └── PaymentManager.php
└── Support/
    └── MerchantContext.php
```

## Requirements

- PHP 8.2+
- Laravel 12.0+
- Stripe PHP SDK ^19.3

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd paycore-backend
```

2. Install dependencies:
```bash
composer install
```

3. Copy environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Run migrations:
```bash
php artisan migrate
```

6. Start the development server:
```bash
php artisan serve
```

## Usage

### Register a Merchant

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "merchant@example.com", "password": "password", "name": "Merchant Name"}'
```

### Create Payment

```bash
curl -X POST http://localhost:8000/api/payments/stripe/one-time \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{"amount": 1000, "currency": "usd", "customer_email": "customer@example.com"}'
```

## Security

If you discover a security vulnerability within this project, please send an e-mail to the maintainers. All security vulnerabilities will be promptly addressed.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
