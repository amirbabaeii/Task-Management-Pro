<?php

namespace App\Models;

use App\Enums\TaskActivityKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'user_id',
        'kind',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TaskActivityKind::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
