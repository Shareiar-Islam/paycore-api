<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantUserToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'merchant_name' => ['required', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $merchant = Merchant::create([
                'owner_user_id' => $user->id,
                'name' => $data['merchant_name'],
                'slug' => $this->uniqueMerchantSlug($data['merchant_name']),
            ]);

            $user->forceFill(['merchant_id' => $merchant->id])->save();

            return $user->fresh('merchant');
        });

        [$plainToken, $token] = $this->createUserToken($user, $user->merchant_id);

        return response()->json([
            'token' => $plainToken,
            'token_expires_at' => $token->expires_at,
            'user' => $user,
            'merchant' => $user->merchant,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (! $user->merchant_id) {
            return response()->json(['message' => 'User is not linked to a merchant.'], 403);
        }

        [$plainToken, $token] = $this->createUserToken($user, $user->merchant_id);

        return response()->json([
            'token' => $plainToken,
            'token_expires_at' => $token->expires_at,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = MerchantUserToken::query()
            ->where('token_hash', hash('sha256', (string) $request->bearerToken()))
            ->first();

        if ($token) {
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    private function uniqueMerchantSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function createUserToken(User $user, int $merchantId): array
    {
        $plain = 'mtu_'.Str::random(64);

        $token = MerchantUserToken::create([
            'merchant_id' => $merchantId,
            'user_id' => $user->id,
            'name' => 'api-session',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$plain, $token];
    }
}
