<?php

namespace App\Services\Interfaces;

interface UserServiceInterface
{
    /**
     * Register a new user
     *
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    public function register(array $data);
} 