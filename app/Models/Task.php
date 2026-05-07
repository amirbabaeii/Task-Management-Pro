<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'tags',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline_at' => 'datetime',
        'progress' => 'integer',
        'priority' => TaskPriority::class,
        'tags' => 'array',
    ];

    /**
     * Limits for task tags.
     */
    public const MAX_TAGS = 10;

    public const MAX_TAG_LENGTH = 30;

    /**
     * Get the users associated with the task.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps();
    }

    /**
     * Get only the assignees for this task.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'assignee');
    }

    /**
     * Get only the reviewers for this task.
     */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('board_id', 'role', 'sort_order')
            ->withTimestamps()
            ->wherePivot('role', 'reviewer');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Normalize tag input from request payloads or persisted data.
     *
     * @param  array<int, mixed>|string|null  $tags
     * @return array<int, string>
     */
    public static function normalizeTags(array|string|null $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[\r\n,]+/', $tags) ?: [];
        }

        return Collection::make($tags ?? [])
            ->map(fn ($tag): string => preg_replace('/\s+/', ' ', trim((string) $tag)) ?: '')
            ->filter()
            ->unique(fn (string $tag): string => strtolower($tag))
            ->values()
            ->all();
    }
}
