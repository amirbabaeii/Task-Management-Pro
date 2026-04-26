<?php

namespace App\Services\Interfaces;

interface UserServiceInterface
{
    /**
     * Register a new user
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function register(array $data);
}
