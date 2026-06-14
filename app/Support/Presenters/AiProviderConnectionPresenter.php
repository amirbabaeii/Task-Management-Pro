<?php

namespace App\Support\Presenters;

use App\Models\AiProviderConnection;

class AiProviderConnectionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(?AiProviderConnection $connection): array
    {
        return [
            'configured' => $connection !== null,
            'provider' => $connection?->provider?->value ?? 'openai',
            'default_model' => $connection?->default_model
                ?? AiProviderConnection::DEFAULT_MODEL,
            'verified_at' => $connection?->verified_at,
            'updated_at' => $connection?->updated_at,
        ];
    }
}
