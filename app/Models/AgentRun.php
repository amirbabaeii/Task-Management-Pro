<?php

namespace App\Models;

use App\Enums\AgentAutonomy;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_id',
        'task_id',
        'agent_id',
        'manager_id',
        'provider_connection_id',
        'provider',
        'model',
        'autonomy',
        'status',
        'summary',
        'rationale',
        'error_code',
        'error_message',
        'provider_response_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'context_snapshot',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'autonomy' => AgentAutonomy::class,
            'status' => AgentRunStatus::class,
            'context_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(
            AiProviderConnection::class,
            'provider_connection_id',
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AgentRunAction::class)
            ->orderBy('id');
    }
}
