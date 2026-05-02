<?php

namespace App\Repositories;

use App\Models\Merchant;
use App\Models\MerchantUserToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthRepository
{
    public function findUserByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function createUserWithMerchant(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $merchant = Merchant::create([
                'owner_user_id' => $user->id,
                'name'          => $data['merchant_name'],
                'slug'          => $this->uniqueMerchantSlug($data['merchant_name']),
            ]);

            $user->forceFill(['merchant_id' => $merchant->id])->save();

            return $user->fresh('merchant');
        });
    }

    public function createToken(User $user, int $merchantId): array
    {
        $plain = 'mtu_'.Str::random(64);

        $token = MerchantUserToken::create([
            'merchant_id' => $merchantId,
            'user_id'     => $user->id,
            'name'        => 'api-session',
            'token_hash'  => hash('sha256', $plain),
            'expires_at'  => now()->addDay(),
        ]);

        return [$plain, $token];
    }

    public function revokeTokenByHash(string $bearerToken): void
    {
        $token = MerchantUserToken::query()
            ->where('token_hash', hash('sha256', $bearerToken))
            ->first();

        if ($token) {
            $token->forceFill(['revoked_at' => now()])->save();
        }
    }

    private function uniqueMerchantSlug(string $name): string
    {
        $base    = Str::slug($name);
        $slug    = $base;
        $counter = 1;

        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
