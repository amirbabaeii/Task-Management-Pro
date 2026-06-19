<?php

namespace App\Services\Ai;

use App\Enums\AgentProviderErrorCode;
use App\Enums\AiProvider;
use App\Enums\TaskPriority;
use App\Exceptions\Agents\AgentProviderException;
use App\Models\AiProviderConnection;
use App\Services\Ai\Contracts\AgentProvider;
use App\Services\Ai\Data\AgentRunPrompt;
use App\Services\Ai\Data\AgentRunResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

class OpenAiAgentProvider implements AgentProvider
{
    public function provider(): AiProvider
    {
        return AiProvider::OpenAI;
    }

    public function verify(AiProviderConnection $connection): void
    {
        try {
            $response = $this->client($connection)->get(
                '/models/'.rawurlencode($connection->default_model),
            );
        } catch (ConnectionException) {
            throw new AgentProviderException(
                AgentProviderErrorCode::TimedOut,
                'OpenAI did not respond before the connection timed out.',
                true,
            );
        }

        $this->ensureSuccessful($response);
    }

    public function execute(
        AiProviderConnection $connection,
        AgentRunPrompt $prompt,
    ): AgentRunResult {
        try {
            $response = $this->client($connection)->post('/responses', [
                'model' => $prompt->model,
                'store' => false,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt->systemInstructions,
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $prompt->contextAsJson(),
                            ],
                        ],
                    ],
                ],
                'reasoning' => [
                    'effort' => config('ai.providers.openai.reasoning_effort'),
                ],
                'max_output_tokens' => config('ai.max_output_tokens'),
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'agent_run_result',
                        'strict' => true,
                        'schema' => $this->resultSchema(),
                    ],
                ],
            ]);
        } catch (ConnectionException) {
            throw new AgentProviderException(
                AgentProviderErrorCode::TimedOut,
                'OpenAI did not respond before the run timed out.',
                true,
            );
        } catch (JsonException) {
            throw $this->malformedOutput();
        }

        $this->ensureSuccessful($response);

        return $this->parseResult($response);
    }

    private function client(AiProviderConnection $connection): PendingRequest
    {
        return Http::baseUrl(
            rtrim((string) config('ai.providers.openai.base_url'), '/'),
        )
            ->acceptJson()
            ->asJson()
            ->withToken($connection->api_key)
            ->timeout((int) config('ai.providers.openai.timeout'));
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw match ($response->status()) {
            401, 403 => new AgentProviderException(
                AgentProviderErrorCode::InvalidCredentials,
                'OpenAI rejected the configured API key or model access.',
            ),
            429 => new AgentProviderException(
                AgentProviderErrorCode::RateLimited,
                'OpenAI rate limited the request. Try again later.',
                true,
            ),
            500, 502, 503, 504 => new AgentProviderException(
                AgentProviderErrorCode::ProviderUnavailable,
                'OpenAI is temporarily unavailable.',
                true,
            ),
            default => new AgentProviderException(
                AgentProviderErrorCode::ProviderError,
                'OpenAI could not complete the request.',
            ),
        };
    }

    private function parseResult(Response $response): AgentRunResult
    {
        $body = $response->json();
        $text = collect($body['output'] ?? [])
            ->flatMap(fn (array $item): array => $item['content'] ?? [])
            ->first(fn (array $item): bool => ($item['type'] ?? null) === 'output_text')['text']
            ?? null;

        if (! is_string($text) || $text === '') {
            throw $this->malformedOutput();
        }

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->malformedOutput();
        }

        if (! is_array($decoded)) {
            throw $this->malformedOutput();
        }

        $validator = Validator::make($decoded, [
            'summary' => ['required', 'string', 'max:2000'],
            'rationale' => ['required', 'string', 'max:5000'],
            'actions' => ['required', 'array', 'max:20'],
            'actions.*.type' => [
                'required',
                Rule::in([
                    'add_comment',
                    'add_checklist_item',
                    'toggle_checklist_item',
                    'update_progress',
                    'change_status',
                    'update_task_fields',
                ]),
            ],
            'actions.*.comment' => ['nullable', 'string', 'max:5000'],
            'actions.*.title' => ['nullable', 'string', 'max:500'],
            'actions.*.checklist_item_id' => ['nullable', 'integer', 'min:1'],
            'actions.*.completed' => ['nullable', 'boolean'],
            'actions.*.progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'actions.*.status' => ['nullable', 'string', 'max:120'],
            'actions.*.fields' => ['required', 'array'],
            'actions.*.fields.title' => ['nullable', 'string', 'max:150'],
            'actions.*.fields.description' => ['nullable', 'string', 'max:1000'],
            'actions.*.fields.tags' => ['nullable', 'array', 'max:10'],
            'actions.*.fields.tags.*' => ['string', 'max:30'],
            'actions.*.fields.priority' => ['nullable', 'string', Rule::in(TaskPriority::values())],
            'actions.*.fields.deadline_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw $this->malformedOutput();
        }

        $this->validateActionPayloads($decoded['actions']);

        return new AgentRunResult(
            summary: $decoded['summary'],
            rationale: $decoded['rationale'],
            actions: $decoded['actions'],
            usage: [
                'input_tokens' => max(0, (int) data_get($body, 'usage.input_tokens', 0)),
                'output_tokens' => max(0, (int) data_get($body, 'usage.output_tokens', 0)),
                'total_tokens' => max(0, (int) data_get($body, 'usage.total_tokens', 0)),
            ],
            providerResponseId: is_string($body['id'] ?? null)
                ? $body['id']
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultSchema(): array
    {
        $nullableString = ['string', 'null'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'rationale', 'actions'],
            'properties' => [
                'summary' => ['type' => 'string'],
                'rationale' => ['type' => 'string'],
                'actions' => [
                    'type' => 'array',
                    'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'type',
                            'comment',
                            'title',
                            'checklist_item_id',
                            'completed',
                            'progress',
                            'status',
                            'fields',
                        ],
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => [
                                    'add_comment',
                                    'add_checklist_item',
                                    'toggle_checklist_item',
                                    'update_progress',
                                    'change_status',
                                    'update_task_fields',
                                ],
                            ],
                            'comment' => ['type' => $nullableString],
                            'title' => ['type' => $nullableString],
                            'checklist_item_id' => [
                                'type' => ['integer', 'null'],
                            ],
                            'completed' => [
                                'type' => ['boolean', 'null'],
                            ],
                            'progress' => [
                                'type' => ['integer', 'null'],
                            ],
                            'status' => ['type' => $nullableString],
                            'fields' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => [
                                    'title',
                                    'description',
                                    'tags',
                                    'priority',
                                    'deadline_at',
                                ],
                                'properties' => [
                                    'title' => ['type' => $nullableString],
                                    'description' => ['type' => $nullableString],
                                    'tags' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'priority' => ['type' => $nullableString],
                                    'deadline_at' => ['type' => $nullableString],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    private function validateActionPayloads(array $actions): void
    {
        foreach ($actions as $action) {
            $fields = $action['fields'] ?? [];
            $valid = match ($action['type'] ?? null) {
                'add_comment' => $this->filledString($action['comment'] ?? null),
                'add_checklist_item' => $this->filledString($action['title'] ?? null),
                'toggle_checklist_item' => is_int($action['checklist_item_id'] ?? null)
                    && ($action['checklist_item_id'] ?? 0) > 0
                    && is_bool($action['completed'] ?? null),
                'update_progress' => is_int($action['progress'] ?? null),
                'change_status' => $this->filledString($action['status'] ?? null),
                'update_task_fields' => is_array($fields)
                    && $this->containsTaskFieldUpdate($fields),
                default => false,
            };

            if (! $valid) {
                throw $this->malformedOutput();
            }
        }
    }

    private function filledString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function containsTaskFieldUpdate(array $fields): bool
    {
        foreach (['title', 'description', 'tags', 'priority', 'deadline_at'] as $field) {
            if (array_key_exists($field, $fields) && $fields[$field] !== null) {
                return true;
            }
        }

        return false;
    }

    private function malformedOutput(): AgentProviderException
    {
        return new AgentProviderException(
            AgentProviderErrorCode::MalformedOutput,
            'OpenAI returned an invalid structured result.',
        );
    }
}
