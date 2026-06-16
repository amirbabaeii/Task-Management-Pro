<?php

namespace Tests\Feature;

use App\Actions\AgentRuns\ApplyAgentRunAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\AgentAutonomy;
use App\Enums\AgentProviderErrorCode;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use App\Enums\BoardRole;
use App\Exceptions\Agents\AgentProviderException;
use App\Jobs\Agents\ExecuteAgentRunJob;
use App\Models\AgentRun;
use App\Models\AiProviderConnection;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Notifications\AgentRunApprovalRequiredNotification;
use App\Notifications\AgentRunFailedNotification;
use App\Services\Ai\AgentProviderManager;
use App\Services\Ai\Contracts\AgentProvider;
use App\Services\Ai\Data\AgentRunPrompt;
use App\Services\Ai\Data\AgentRunResult;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class AgentRunExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_run_persists_provider_result_for_review(): void
    {
        Notification::fake();

        $run = $this->runFixture(AgentAutonomy::Approval);
        $capturedPrompt = null;

        $this->fakeProviderReturning(new AgentRunResult(
            summary: 'A checklist will clarify the work.',
            rationale: 'The task has an outcome but no breakdown.',
            actions: [
                [
                    'type' => AgentRunActionType::AddChecklistItem->value,
                    'title' => 'Write regression tests',
                ],
            ],
            usage: [
                'input_tokens' => 100,
                'output_tokens' => 40,
                'total_tokens' => 140,
            ],
            providerResponseId: 'resp_123',
        ), $capturedPrompt);

        (new ExecuteAgentRunJob($run))->handle(
            app(AgentProviderManager::class),
            app(ApplyAgentRunAction::class),
        );

        $run->refresh();

        $this->assertSame(AgentRunStatus::AwaitingApproval, $run->status);
        $this->assertSame('A checklist will clarify the work.', $run->summary);
        $this->assertSame('resp_123', $run->provider_response_id);
        $this->assertSame(140, $run->total_tokens);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertSame('Write release checklist', $capturedPrompt->context['task']['title']);

        $action = $run->actions()->firstOrFail();

        $this->assertSame(AgentRunActionType::AddChecklistItem, $action->type);
        $this->assertSame(AgentRunActionStatus::Proposed, $action->status);
        $this->assertSame('Write regression tests', $action->payload['title']);

        Notification::assertSentTo(
            $run->manager,
            AgentRunApprovalRequiredNotification::class,
        );
    }

    public function test_advisory_run_persists_suggestions_without_approval_state(): void
    {
        $run = $this->runFixture(AgentAutonomy::Advisory);

        $this->fakeProviderReturning(new AgentRunResult(
            summary: 'Consider adding context.',
            rationale: 'The next reviewer will need more details.',
            actions: [
                [
                    'type' => AgentRunActionType::AddComment->value,
                    'comment' => 'Add acceptance criteria before implementation.',
                ],
            ],
            usage: [
                'input_tokens' => 50,
                'output_tokens' => 20,
                'total_tokens' => 70,
            ],
        ));

        (new ExecuteAgentRunJob($run))->handle(
            app(AgentProviderManager::class),
            app(ApplyAgentRunAction::class),
        );

        $run->refresh();

        $this->assertSame(AgentRunStatus::Completed, $run->status);
        $this->assertSame(
            AgentRunActionStatus::Suggested,
            $run->actions()->firstOrFail()->status,
        );
    }

    public function test_provider_error_marks_run_failed_with_sanitized_error(): void
    {
        Notification::fake();

        $run = $this->runFixture(AgentAutonomy::Approval);

        $this->fakeProviderThrowing(new AgentProviderException(
            AgentProviderErrorCode::InvalidCredentials,
            'OpenAI rejected the configured API key or model access.',
        ));

        (new ExecuteAgentRunJob($run))->handle(
            app(AgentProviderManager::class),
            app(ApplyAgentRunAction::class),
        );

        $run->refresh();

        $this->assertSame(AgentRunStatus::Failed, $run->status);
        $this->assertSame(AgentProviderErrorCode::InvalidCredentials->value, $run->error_code);
        $this->assertSame(
            'OpenAI rejected the configured API key or model access.',
            $run->error_message,
        );
        $this->assertNotNull($run->failed_at);

        Notification::assertSentTo($run->manager, AgentRunFailedNotification::class);
    }

    public function test_retryable_provider_error_does_not_strand_running_run(): void
    {
        Notification::fake();

        $run = $this->runFixture(AgentAutonomy::Approval);
        $provider = new class implements AgentProvider
        {
            public int $calls = 0;

            public function provider(): AiProvider
            {
                return AiProvider::OpenAI;
            }

            public function verify(AiProviderConnection $connection): void {}

            public function execute(
                AiProviderConnection $connection,
                AgentRunPrompt $prompt,
            ): AgentRunResult {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new AgentProviderException(
                        AgentProviderErrorCode::RateLimited,
                        'OpenAI rate limited the request. Try again later.',
                        true,
                    );
                }

                return new AgentRunResult(
                    summary: 'Recovered after retry.',
                    rationale: 'The provider accepted the second attempt.',
                    actions: [],
                    usage: [
                        'input_tokens' => 10,
                        'output_tokens' => 5,
                        'total_tokens' => 15,
                    ],
                    providerResponseId: 'resp_retry',
                );
            }
        };

        $this->mock(
            AgentProviderManager::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('for')
                ->twice()
                ->with(AiProvider::OpenAI)
                ->andReturn($provider),
        );

        $firstAttempt = new ExecuteAgentRunJob($run);
        $firstAttempt->setJob($this->queueJobAttempt(1));

        try {
            $firstAttempt->handle(
                app(AgentProviderManager::class),
                app(ApplyAgentRunAction::class),
            );
            $this->fail('Expected retryable provider exception.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderErrorCode::RateLimited, $exception->errorCode);
        }

        $this->assertSame(AgentRunStatus::Running, $run->fresh()->status);

        $secondAttempt = new ExecuteAgentRunJob($run);
        $secondAttempt->setJob($this->queueJobAttempt(2));

        $secondAttempt->handle(
            app(AgentProviderManager::class),
            app(ApplyAgentRunAction::class),
        );

        $run->refresh();

        $this->assertSame(AgentRunStatus::Completed, $run->status);
        $this->assertSame('Recovered after retry.', $run->summary);
        $this->assertSame('resp_retry', $run->provider_response_id);
        Notification::assertNothingSent();
    }

    public function test_retry_exhaustion_marks_run_failed_and_notifies_manager(): void
    {
        Notification::fake();

        $run = $this->runFixture(AgentAutonomy::Approval);
        $exception = new AgentProviderException(
            AgentProviderErrorCode::TimedOut,
            'OpenAI did not respond before the run timed out.',
            true,
        );

        (new ExecuteAgentRunJob($run))->failed($exception);

        $run->refresh();

        $this->assertSame(AgentRunStatus::Failed, $run->status);
        $this->assertSame(AgentProviderErrorCode::TimedOut->value, $run->error_code);
        $this->assertSame(
            'OpenAI did not respond before the run timed out.',
            $run->error_message,
        );
        $this->assertNotNull($run->failed_at);
        Notification::assertSentTo($run->manager, AgentRunFailedNotification::class);
    }

    public function test_automatic_run_applies_safe_actions_and_holds_field_changes(): void
    {
        $run = $this->runFixture(AgentAutonomy::Automatic);
        $task = $run->task;
        $agent = $run->agent;
        $item = $task->checklistItems()->create([
            'title' => 'Draft notes',
            'position' => 1,
        ]);

        $this->fakeProviderReturning(new AgentRunResult(
            summary: 'Safe updates were ready to apply.',
            rationale: 'The actions only touch comments, checklist, progress, and status.',
            actions: [
                [
                    'type' => AgentRunActionType::AddComment->value,
                    'comment' => 'I added the implementation checklist.',
                ],
                [
                    'type' => AgentRunActionType::AddChecklistItem->value,
                    'title' => 'Run regression suite',
                ],
                [
                    'type' => AgentRunActionType::ToggleChecklistItem->value,
                    'checklist_item_id' => $item->id,
                    'completed' => true,
                ],
                [
                    'type' => AgentRunActionType::UpdateProgress->value,
                    'progress' => 55,
                ],
                [
                    'type' => AgentRunActionType::ChangeStatus->value,
                    'status' => 'in-progress',
                ],
                [
                    'type' => AgentRunActionType::UpdateTaskFields->value,
                    'fields' => [
                        'title' => 'Needs manager approval',
                    ],
                ],
            ],
            usage: [
                'input_tokens' => 80,
                'output_tokens' => 30,
                'total_tokens' => 110,
            ],
        ));

        (new ExecuteAgentRunJob($run))->handle(
            app(AgentProviderManager::class),
            app(ApplyAgentRunAction::class),
        );

        $run->refresh();
        $task->refresh();

        $this->assertSame(AgentRunStatus::AwaitingApproval, $run->status);
        $this->assertSame('in-progress', $task->status);
        $this->assertSame(55, $task->progress);
        $this->assertSame('Write release checklist', $task->title);
        $this->assertTrue($item->fresh()->completed_at !== null);
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $agent->id,
            'content' => 'I added the implementation checklist.',
        ]);
        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $task->id,
            'title' => 'Run regression suite',
        ]);

        $statuses = $run->actions()
            ->orderBy('id')
            ->get()
            ->map(fn ($action): string => $action->status->value)
            ->all();

        $this->assertSame([
            AgentRunActionStatus::Applied->value,
            AgentRunActionStatus::Applied->value,
            AgentRunActionStatus::Applied->value,
            AgentRunActionStatus::Applied->value,
            AgentRunActionStatus::Applied->value,
            AgentRunActionStatus::Proposed->value,
        ], $statuses);

        $this->assertTrue(TaskActivity::query()
            ->where('task_id', $task->id)
            ->where('user_id', $agent->id)
            ->exists());
    }

    private function fakeProviderReturning(
        AgentRunResult $result,
        ?AgentRunPrompt &$capturedPrompt = null,
    ): void {
        $provider = new class($result, $capturedPrompt) implements AgentProvider
        {
            public function __construct(
                private readonly AgentRunResult $result,
                private ?AgentRunPrompt &$capturedPrompt,
            ) {}

            public function provider(): AiProvider
            {
                return AiProvider::OpenAI;
            }

            public function verify(AiProviderConnection $connection): void {}

            public function execute(
                AiProviderConnection $connection,
                AgentRunPrompt $prompt,
            ): AgentRunResult {
                $this->capturedPrompt = $prompt;

                return $this->result;
            }
        };

        $this->mockProviderManager($provider);
    }

    private function fakeProviderThrowing(AgentProviderException $exception): void
    {
        $provider = new class($exception) implements AgentProvider
        {
            public function __construct(
                private readonly AgentProviderException $exception,
            ) {}

            public function provider(): AiProvider
            {
                return AiProvider::OpenAI;
            }

            public function verify(AiProviderConnection $connection): void {}

            public function execute(
                AiProviderConnection $connection,
                AgentRunPrompt $prompt,
            ): AgentRunResult {
                throw $this->exception;
            }
        };

        $this->mockProviderManager($provider);
    }

    private function queueJobAttempt(int $attempt): QueueJob
    {
        $job = \Mockery::mock(QueueJob::class);
        $job->shouldReceive('attempts')->andReturn($attempt);

        return $job;
    }

    private function mockProviderManager(AgentProvider $provider): void
    {
        $this->mock(
            AgentProviderManager::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('for')
                ->once()
                ->with(AiProvider::OpenAI)
                ->andReturn($provider),
        );
    }

    private function runFixture(AgentAutonomy $autonomy): AgentRun
    {
        $manager = User::factory()->create();
        $board = app(EnsureUserHasDefaultBoardAction::class)->execute($manager);
        $connection = $manager->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-manager-secret',
            'default_model' => AiProviderConnection::DEFAULT_MODEL,
            'verified_at' => now(),
        ]);
        $agent = User::factory()->create([
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
            'agent_provider_connection_id' => $connection->id,
            'agent_autonomy' => $autonomy,
        ]);
        $task = Task::factory()->create([
            'title' => 'Write release checklist',
        ]);

        $board->members()->attach($agent->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);
        $task->users()->attach($agent->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        return AgentRun::query()->create([
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'provider_connection_id' => $connection->id,
            'provider' => AiProvider::OpenAI,
            'model' => AiProviderConnection::DEFAULT_MODEL,
            'autonomy' => $autonomy,
            'status' => AgentRunStatus::Queued,
            'context_snapshot' => [
                'task' => [
                    'title' => $task->title,
                ],
            ],
        ]);
    }
}
