<?php

use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/tasks/board/{board?}', [BoardController::class, 'index'])
        ->name('tasks.board');
    Route::post('/boards', [BoardController::class, 'store'])
        ->name('boards.store');
    Route::patch('/boards/{board}', [BoardController::class, 'update'])
        ->name('boards.update');

    Route::get('/boards/{board}/members', [BoardMemberController::class, 'index'])
        ->name('boards.members.index');
    Route::post('/boards/{board}/members', [BoardMemberController::class, 'store'])
        ->name('boards.members.store');
    Route::delete('/boards/{board}/members/{user}', [BoardMemberController::class, 'destroy'])
        ->name('boards.members.destroy');

    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->name('tasks.comments.store');

    Route::post('/tasks/board/{board}/columns', [BoardColumnController::class, 'store'])
        ->name('tasks.columns.store');
    Route::patch('/tasks/board/{board}/columns/{status}/reorder', [BoardColumnController::class, 'reorder'])
        ->name('tasks.columns.reorder');
    Route::delete('/tasks/board/{board}/columns/{status}', [BoardColumnController::class, 'destroy'])
        ->name('tasks.columns.destroy');
    Route::patch('/tasks/board/{board}/status-labels/{status}', [BoardColumnController::class, 'updateLabel'])
        ->name('tasks.status-labels.update');

    Route::post('/tasks/board/{board}/tasks', [TaskController::class, 'store'])
        ->name('tasks.store');
    Route::patch('/tasks/board/{board}/tasks/{task}', [TaskController::class, 'update'])
        ->name('tasks.update');
    Route::patch('/tasks/board/{board}/tasks/{task}/reorder', [TaskController::class, 'reorder'])
        ->name('tasks.reorder');
    Route::patch('/tasks/board/{board}/tasks/{task}/status', [TaskController::class, 'updateStatus'])
        ->name('tasks.status');
    Route::patch('/tasks/board/{board}/tasks/{task}/archive', [TaskController::class, 'archive'])
        ->name('tasks.archive');
    Route::patch('/tasks/board/{board}/tasks/{task}/restore', [TaskController::class, 'restore'])
        ->name('tasks.restore');
    Route::delete('/tasks/board/{board}/tasks/{task}', [TaskController::class, 'destroy'])
        ->name('tasks.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
