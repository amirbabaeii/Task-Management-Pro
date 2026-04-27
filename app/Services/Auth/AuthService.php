<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AuthenticationFailedException;
use App\Models\User;
use App\Services\Interfaces\Auth\AuthServiceInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AuthService implements AuthServiceInterface
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{token: string, user: User}
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw new AuthenticationFailedException('Invalid credentials');
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(User $user): bool
    {
        $token = $user->currentAccessToken();

        if ($token === null || ! $token->delete()) {
            throw new RuntimeException('Failed to logout user');
        }

        return true;
    }
}
