<?php

namespace App\Exceptions\Agents;

use App\Enums\AgentProviderErrorCode;
use RuntimeException;

class AgentProviderException extends RuntimeException
{
    public function __construct(
        public readonly AgentProviderErrorCode $errorCode,
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
