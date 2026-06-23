<?php

namespace App\Actions\AiConnections;

use App\Enums\AiProvider;
use App\Models\AiProviderConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveOpenAiConnectionAction
{
    /**
     * @param  array{api_key?: string|null, default_model: string}  $data
     */
    public function execute(User $manager, array $data): AiProviderConnection
    {
        return DB::transaction(function () use ($manager, $data): AiProviderConnection {
            $connection = $manager->openAiConnection()->first()
                ?? new AiProviderConnection([
                    'user_id' => $manager->id,
                    'provider' => AiProvider::OpenAI,
                ]);

            $modelChanged = $connection->exists
                && $connection->default_model !== $data['default_model'];

            $connection->default_model = $data['default_model'];

            if (filled($data['api_key'] ?? null)) {
                $connection->api_key = $data['api_key'];
                $connection->verified_at = null;
            }

            if ($modelChanged) {
                $connection->verified_at = null;
            }

            $connection->save();

            return $connection->fresh();
        });
    }
}
