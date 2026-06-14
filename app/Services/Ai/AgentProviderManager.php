<?php

namespace App\Services\Ai;

use App\Enums\AiProvider;
use App\Services\Ai\Contracts\AgentProvider;
use InvalidArgumentException;

class AgentProviderManager
{
    public function __construct(
        private readonly OpenAiAgentProvider $openAi,
    ) {}

    public function for(AiProvider $provider): AgentProvider
    {
        return match ($provider) {
            AiProvider::OpenAI => $this->openAi,
        };
    }

    public function forValue(string $provider): AgentProvider
    {
        $resolved = AiProvider::tryFrom($provider);

        if (! $resolved) {
            throw new InvalidArgumentException("Unsupported AI provider [{$provider}].");
        }

        return $this->for($resolved);
    }
}
