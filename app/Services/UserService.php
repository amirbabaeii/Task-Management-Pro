<?php

namespace App\Services;

use App\Models\User;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserService implements UserServiceInterface
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        try {
            return DB::transaction(fn (): User => User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]));
        } catch (Throwable $e) {
            Log::error('User registration failed: '.$e->getMessage());

            throw $e;
        }
    }
}
