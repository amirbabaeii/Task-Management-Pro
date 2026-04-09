<?php

namespace App\Providers;

use App\Models\Task;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Gate;
use App\Services\Auth\AuthService;
use App\Services\Interfaces\Auth\AuthServiceInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Exceptions\ApiExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        
        $this->app->bind(ExceptionHandlerContract::class, ApiExceptionHandler::class);
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        JsonResource::withoutWrapping();
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
