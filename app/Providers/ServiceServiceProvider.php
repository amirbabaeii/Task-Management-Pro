<?php

namespace App\Providers;

use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService(
                $app->make(\App\Repositories\Interfaces\UserRepositoryInterface::class)
            );
        });
    }
} 