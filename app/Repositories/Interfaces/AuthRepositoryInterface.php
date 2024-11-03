<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface AuthRepositoryInterface
{
    public function authenticateUser(array $credentials): ?User;
    public function createUserToken(User $user, string $tokenName): string;
    public function deleteCurrentToken(User $user): bool;
} 