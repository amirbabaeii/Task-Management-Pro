<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

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
     * Get all tasks associated with the user.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get tasks where user is an assignee.
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', 'assignee');
    }

    /**
     * Get tasks where user is a reviewer.
     */
    public function reviewingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivot('role', 'reviewer');
    }
}
