<?php

namespace App\Actions\AiConnections;

use App\Models\AiProviderConnection;

class DeleteOpenAiConnectionAction
{
    public function execute(AiProviderConnection $connection): void
    {
        $connection->delete();
    }
}
