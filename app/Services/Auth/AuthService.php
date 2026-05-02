<?php

namespace App\Services\Auth;

use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly AuthRepository $repository) {}

    public function register(array $data): array
    {
        $user = $this->repository->createUserWithMerchant($data);
        [$plainToken, $token] = $this->repository->createToken($user, $user->merchant_id);

        return [
            'token'            => $plainToken,
            'token_expires_at' => $token->expires_at,
            'user'             => $user,
            'merchant'         => $user->merchant,
        ];
    }

    public function login(array $data): array
    {
        $user = $this->repository->findUserByEmail($data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new \InvalidArgumentException('Invalid credentials.');
        }

        if (! $user->merchant_id) {
            throw new \DomainException('User is not linked to a merchant.');
        }

        [$plainToken, $token] = $this->repository->createToken($user, $user->merchant_id);

        return [
            'token'            => $plainToken,
            'token_expires_at' => $token->expires_at,
            'user'             => $user,
        ];
    }

    public function logout(string $bearerToken): void
    {
        $this->repository->revokeTokenByHash($bearerToken);
    }
}
