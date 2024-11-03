<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API v1 Routes
Route::prefix('v1')->name('api.v1.')->group(function () {
    // Auth routes group
    Route::prefix('auth')->name('auth.')->group(function () {
        // Public auth routes
        Route::post('login', [App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])
            ->name('login');
        Route::post('register', [App\Http\Controllers\Api\V1\Auth\RegisterController::class, 'register'])
            ->name('register');
        
        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])
                ->name('logout');
        });
    });

    // Other protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', function (Request $request) {
            return $request->user();
        })->name('user.profile');
    });
});

