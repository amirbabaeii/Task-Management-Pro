<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;
use App\Exceptions\Auth\AuthenticationFailedException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private AuthRepositoryInterface $authRepository
    ) {}

    public function login(array $credentials): array
    {
        $user = $this->authRepository->authenticateUser($credentials);
        
        if (!$user) {
            throw new AuthenticationFailedException('Invalid credentials');
        }

        $token = $this->authRepository->createUserToken($user, 'auth-token');

        return [
            'token' => $token,
            'user' => $user
        ];
    }

    public function logout(User $user): bool
    {
        if (!$this->authRepository->deleteCurrentToken($user)) {
            throw new \RuntimeException('Failed to logout user');
        }
        return true;
    }
} 