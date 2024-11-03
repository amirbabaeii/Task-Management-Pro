<?php

namespace App\Repositories\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Interfaces\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function authenticateUser(array $credentials): ?User
    {
        if (Auth::attempt($credentials)) {
            return Auth::user();
        }
        return null;
    }

    public function createUserToken(User $user, string $tokenName): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }

    public function deleteCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
} 