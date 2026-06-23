<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProviderConnection extends Model
{
    use HasFactory;

    public const DEFAULT_MODEL = 'gpt-5.4-mini';

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'default_model',
        'verified_at',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class, 'provider_connection_id');
    }
}
