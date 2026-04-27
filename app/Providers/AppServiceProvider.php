<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\TaskPolicy;
use App\Services\Auth\AuthService;
use App\Services\Interfaces\Auth\AuthServiceInterface;
use App\Services\UserService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->singleton(UserService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        JsonResource::withoutWrapping();

        Gate::policy(Task::class, TaskPolicy::class);

        User::observe(UserObserver::class);
    }
}
