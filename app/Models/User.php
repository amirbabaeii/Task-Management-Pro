<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_agent',
        'agent_manager_id',
        'agent_title',
        'agent_profile',
        'agent_personality',
        'agent_skills',
        'agent_archived_at',
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
            'is_agent' => 'boolean',
            'agent_skills' => 'array',
            'agent_archived_at' => 'datetime',
        ];
    }

    public function agentManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_manager_id');
    }

    public function managedAgents(): HasMany
    {
        return $this->hasMany(User::class, 'agent_manager_id')
            ->where('is_agent', true);
    }

    public function aiProviderConnections(): HasMany
    {
        return $this->hasMany(AiProviderConnection::class);
    }

    public function openAiConnection(): HasOne
    {
        return $this->hasOne(AiProviderConnection::class)
            ->where('provider', 'openai');
    }

    public function scopeAgentsManagedBy(Builder $query, User $manager, ?bool $archived = null): Builder
    {
        return $query
            ->where('is_agent', true)
            ->where('agent_manager_id', $manager->id)
            ->when(
                $archived !== null,
                fn (Builder $query): Builder => $archived
                    ? $query->whereNotNull('agent_archived_at')
                    : $query->whereNull('agent_archived_at'),
            );
    }

    public function scopeWithAgentWorkload(Builder $query): Builder
    {
        return $query
            ->with([
                'accessibleBoards' => fn ($query) => $query
                    ->select([
                        'boards.id',
                        'boards.name',
                    ])
                    ->orderByRaw("CASE board_members.role WHEN 'owner' THEN 0 ELSE 1 END")
                    ->orderBy('boards.name')
                    ->orderBy('boards.id'),
                'assignedTasks' => fn ($query) => $query
                    ->whereNull('tasks.archived_at')
                    ->select([
                        'tasks.id',
                        'tasks.title',
                        'tasks.status',
                        'tasks.priority',
                        'tasks.deadline_at',
                    ])
                    ->orderByRaw('tasks.deadline_at is null')
                    ->orderBy('tasks.deadline_at')
                    ->orderBy('tasks.id'),
            ])
            ->withCount([
                'accessibleBoards as boards_count',
                'assignedTasks as active_tasks_count' => fn (Builder $query): Builder => $query
                    ->whereNull('tasks.archived_at'),
                'assignedTasks as overdue_tasks_count' => fn (Builder $query): Builder => $query
                    ->whereNull('tasks.archived_at')
                    ->whereNotNull('tasks.deadline_at')
                    ->where('tasks.deadline_at', '<', now()),
            ]);
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
