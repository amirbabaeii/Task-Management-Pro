<?php

namespace App\Services\Ai\Data;

class AgentRunPrompt
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $model,
        public readonly string $systemInstructions,
        public readonly array $context,
    ) {}

    public function contextAsJson(): string
    {
        return json_encode(
            $this->context,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
