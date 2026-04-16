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
    Route::get('/tasks/board/{board?}', [TaskBoardController::class, 'index'])
        ->name('tasks.board');
    Route::post('/boards', [TaskBoardController::class, 'storeBoard'])
        ->name('boards.store');
    Route::patch('/boards/{board}', [TaskBoardController::class, 'updateBoard'])
        ->name('boards.update');
    Route::post('/tasks/board/{board}/tasks', [TaskBoardController::class, 'store'])
        ->name('tasks.store');
    Route::post('/tasks/board/{board}/columns', [TaskBoardController::class, 'storeColumn'])
        ->name('tasks.columns.store');
    Route::patch('/tasks/board/{board}/columns/{status}/reorder', [TaskBoardController::class, 'reorderColumn'])
        ->name('tasks.columns.reorder');
    Route::patch('/tasks/board/{board}/status-labels/{status}', [TaskBoardController::class, 'updateStatusLabel'])
        ->name('tasks.status-labels.update');
    Route::patch('/tasks/board/{board}/tasks/{task}', [TaskBoardController::class, 'update'])
        ->name('tasks.update');
    Route::patch('/tasks/board/{board}/tasks/{task}/reorder', [TaskBoardController::class, 'reorder'])
        ->name('tasks.reorder');
    Route::patch('/tasks/board/{board}/tasks/{task}/status', [TaskBoardController::class, 'updateStatus'])
        ->name('tasks.status');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
