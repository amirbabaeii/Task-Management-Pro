<?php

namespace App\Actions\AiConnections;

use App\Models\AiProviderConnection;
use App\Services\Ai\AgentProviderManager;

class VerifyOpenAiConnectionAction
{
    public function __construct(
        private readonly AgentProviderManager $providers,
    ) {}

    public function execute(AiProviderConnection $connection): AiProviderConnection
    {
        $this->providers
            ->for($connection->provider)
            ->verify($connection);

        $connection->forceFill([
            'verified_at' => now(),
        ])->save();

        return $connection->fresh();
    }
}
