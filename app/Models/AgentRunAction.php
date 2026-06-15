<?php

namespace App\Models;

use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRunAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_run_id',
        'type',
        'status',
        'payload',
        'error_message',
        'approved_by',
        'approved_at',
        'rejected_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AgentRunActionType::class,
            'status' => AgentRunActionStatus::class,
            'payload' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
