<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'progress',
        'deadline_at',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline_at' => 'datetime',
        'progress' => 'integer',
    ];


    /**
     * Default status values available on new boards.
     *
     * @var array<string>
     */
    public const STATUSES = [
        'pending',
        'in-progress',
        'completed'
    ];

    /**
     * Valid priority values for the task.
     *
     * @var array<string>
     */
    public const PRIORITIES = [
        'low',
        'medium',
        'high',
    ];

    /**
     * Get the users associated with the task.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Get only the assignees for this task.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'assignee');
    }

    /**
     * Get only the reviewers for this task.
     */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'reviewer');
    }
}
