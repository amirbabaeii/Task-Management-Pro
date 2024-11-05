<?php

namespace App\Services\Interfaces\Auth;

use App\Models\User;

interface AuthServiceInterface
{
    public function login(array $credentials): array;
    public function logout(User $user): bool;
} 