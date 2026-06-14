<?php

namespace App\Services\Ai\Contracts;

use App\Enums\AiProvider;
use App\Models\AiProviderConnection;
use App\Services\Ai\Data\AgentRunPrompt;
use App\Services\Ai\Data\AgentRunResult;

interface AgentProvider
{
    public function provider(): AiProvider;

    public function verify(AiProviderConnection $connection): void;

    public function execute(
        AiProviderConnection $connection,
        AgentRunPrompt $prompt,
    ): AgentRunResult;
}
