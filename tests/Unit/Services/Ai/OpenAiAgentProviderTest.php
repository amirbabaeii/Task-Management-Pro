<?php

namespace Tests\Unit\Services\Ai;

use App\Enums\AgentProviderErrorCode;
use App\Enums\AiProvider;
use App\Exceptions\Agents\AgentProviderException;
use App\Models\AiProviderConnection;
use App\Models\User;
use App\Services\Ai\Data\AgentRunPrompt;
use App\Services\Ai\OpenAiAgentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiAgentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_verification_uses_configured_model(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models/gpt-5.5' => Http::response([
                'id' => 'gpt-5.5',
            ]),
        ]);

        app(OpenAiAgentProvider::class)->verify($this->connection());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.openai.com/v1/models/gpt-5.5'
            && $request->hasHeader('Authorization', 'Bearer sk-test-secret'));
    }

    public function test_execute_uses_responses_api_and_parses_structured_result(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'The task needs a checklist.',
                                    'rationale' => 'Breaking the work down reduces ambiguity.',
                                    'actions' => [
                                        [
                                            'type' => 'add_checklist_item',
                                            'comment' => null,
                                            'title' => 'Write regression tests',
                                            'checklist_item_id' => null,
                                            'completed' => null,
                                            'progress' => null,
                                            'status' => null,
                                            'fields' => [
                                                'title' => null,
                                                'description' => null,
                                                'tags' => null,
                                                'priority' => null,
                                                'deadline_at' => null,
                                            ],
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 120,
                    'output_tokens' => 35,
                    'total_tokens' => 155,
                ],
            ]),
        ]);

        $result = app(OpenAiAgentProvider::class)->execute(
            $this->connection(),
            new AgentRunPrompt(
                model: 'gpt-5.5',
                systemInstructions: 'Analyze the task.',
                context: ['task' => ['title' => 'Ship feature']],
            ),
        );

        $this->assertSame('The task needs a checklist.', $result->summary);
        $this->assertSame('add_checklist_item', $result->actions[0]['type']);
        $this->assertSame(155, $result->usage['total_tokens']);
        $this->assertSame('resp_123', $result->providerResponseId);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['model'] === 'gpt-5.5'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && data_get(
                    $payload,
                    'text.format.schema.properties.actions.items.properties.fields.properties.priority.enum',
                ) === ['low', 'medium', 'high', null];
        });
    }

    public function test_negative_token_usage_is_clamped_to_zero(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'No changes needed.',
                            'rationale' => 'The task is already clear.',
                            'actions' => [],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => -10,
                    'output_tokens' => -5,
                    'total_tokens' => -15,
                ],
            ]),
        ]);

        $result = app(OpenAiAgentProvider::class)->execute(
            $this->connection(),
            new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
        );

        $this->assertSame([
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
        ], $result->usage);
    }

    public function test_provider_errors_are_sanitized(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models/*' => Http::response([
                'error' => [
                    'message' => 'Sensitive upstream detail',
                ],
            ], 401),
        ]);

        try {
            app(OpenAiAgentProvider::class)->verify($this->connection());
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::InvalidCredentials,
                $exception->errorCode,
            );
            $this->assertStringNotContainsString(
                'Sensitive upstream detail',
                $exception->getMessage(),
            );
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_rate_limits_are_retryable(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'error' => [
                    'message' => 'Sensitive rate limit detail',
                ],
            ], 429),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::RateLimited,
                $exception->errorCode,
            );
            $this->assertTrue($exception->retryable);
            $this->assertStringNotContainsString(
                'Sensitive rate limit detail',
                $exception->getMessage(),
            );
        }
    }

    public function test_provider_unavailable_errors_are_retryable(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([], 503),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::ProviderUnavailable,
                $exception->errorCode,
            );
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_connection_timeouts_are_retryable_and_sanitized(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => fn () => throw new ConnectionException('Sensitive timeout detail'),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::TimedOut,
                $exception->errorCode,
            );
            $this->assertTrue($exception->retryable);
            $this->assertStringNotContainsString(
                'Sensitive timeout detail',
                $exception->getMessage(),
            );
        }
    }

    public function test_malformed_output_is_rejected(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => '{"summary":"Missing fields"}',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    public function test_task_field_actions_must_include_at_least_one_update(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'summary' => 'No useful change.',
                                    'rationale' => 'The action only contains schema placeholders.',
                                    'actions' => [
                                        [
                                            'type' => 'update_task_fields',
                                            'comment' => null,
                                            'title' => null,
                                            'checklist_item_id' => null,
                                            'completed' => null,
                                            'progress' => null,
                                            'status' => null,
                                            'fields' => [
                                                'title' => null,
                                                'description' => null,
                                                'tags' => null,
                                                'priority' => null,
                                                'deadline_at' => null,
                                            ],
                                        ],
                                    ],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    public function test_task_field_actions_reject_unsupported_priority(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Raise the priority.',
                            'rationale' => 'The task is urgent.',
                            'actions' => [[
                                'type' => 'update_task_fields',
                                'comment' => null,
                                'title' => null,
                                'checklist_item_id' => null,
                                'completed' => null,
                                'progress' => null,
                                'status' => null,
                                'fields' => [
                                    'title' => null,
                                    'description' => null,
                                    'tags' => null,
                                    'priority' => 'urgent',
                                    'deadline_at' => null,
                                ],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    public function test_actions_reject_unsupported_top_level_keys(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Assign the task.',
                            'rationale' => 'This operation is not allowed.',
                            'actions' => [[
                                'type' => 'add_comment',
                                'comment' => 'This task needs a human owner.',
                                'title' => null,
                                'checklist_item_id' => null,
                                'completed' => null,
                                'progress' => null,
                                'status' => null,
                                'fields' => [
                                    'title' => null,
                                    'description' => null,
                                    'tags' => null,
                                    'priority' => null,
                                    'deadline_at' => null,
                                ],
                                'assignee_ids' => [1],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    public function test_actions_reject_unsupported_task_field_keys(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Archive the task.',
                            'rationale' => 'This operation is not allowed.',
                            'actions' => [[
                                'type' => 'update_task_fields',
                                'comment' => null,
                                'title' => null,
                                'checklist_item_id' => null,
                                'completed' => null,
                                'progress' => null,
                                'status' => null,
                                'fields' => [
                                    'title' => null,
                                    'description' => null,
                                    'tags' => null,
                                    'priority' => null,
                                    'deadline_at' => null,
                                    'archived_at' => now()->toISOString(),
                                ],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    public function test_actions_reject_missing_schema_keys(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Comment on the task.',
                            'rationale' => 'The action omits schema placeholders.',
                            'actions' => [[
                                'type' => 'add_comment',
                                'comment' => 'Please clarify the acceptance criteria.',
                                'title' => null,
                                'checklist_item_id' => null,
                                'completed' => null,
                                'progress' => null,
                                'fields' => [
                                    'title' => null,
                                    'description' => null,
                                    'tags' => null,
                                    'priority' => null,
                                    'deadline_at' => null,
                                ],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        try {
            app(OpenAiAgentProvider::class)->execute(
                $this->connection(),
                new AgentRunPrompt('gpt-5.5', 'Analyze.', []),
            );
            $this->fail('Expected provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(
                AgentProviderErrorCode::MalformedOutput,
                $exception->errorCode,
            );
        }
    }

    private function connection(): AiProviderConnection
    {
        $user = User::factory()->create();

        return $user->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-secret',
            'default_model' => 'gpt-5.5',
        ]);
    }
}
