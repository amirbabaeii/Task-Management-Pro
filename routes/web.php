<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskBoardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/tasks/board', [TaskBoardController::class, 'index'])
        ->name('tasks.board');
    Route::post('/tasks', [TaskBoardController::class, 'store'])
        ->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskBoardController::class, 'update'])
        ->name('tasks.update');
    Route::patch('/tasks/{task}/progress', [TaskBoardController::class, 'updateProgress'])
        ->name('tasks.progress');
    Route::patch('/tasks/{task}/status', [TaskBoardController::class, 'updateStatus'])
        ->name('tasks.status');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
