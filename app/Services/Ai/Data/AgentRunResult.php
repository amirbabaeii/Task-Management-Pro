<?php

namespace App\Services\Ai\Data;

class AgentRunResult
{
    /**
     * @param  list<array<string, mixed>>  $actions
     * @param  array{input_tokens: int, output_tokens: int, total_tokens: int}  $usage
     */
    public function __construct(
        public readonly string $summary,
        public readonly string $rationale,
        public readonly array $actions,
        public readonly array $usage,
        public readonly ?string $providerResponseId = null,
    ) {}
}
