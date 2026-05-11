<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the boards owned by the user.
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    /**
     * Every board this user can access — owned plus boards they collaborate on.
     */
    public function accessibleBoards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_members')
            ->withPivot(['role', 'joined_at', 'filter_preferences'])
            ->withTimestamps();
    }

    /**
     * Get the saved board column settings for the user.
     */
    public function boardColumns(): HasMany
    {
        return $this->hasMany(BoardColumn::class);
    }

    /**
     * Get all tasks associated with the user.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Get tasks where user is an assignee.
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'assignee');
    }

    /**
     * Get tasks where user is a reviewer.
     */
    public function reviewingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'reviewer');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
