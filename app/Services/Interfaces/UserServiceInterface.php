<?php

namespace App\Services\Interfaces;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws \Throwable
     */
    public function register(array $data): User;
}
