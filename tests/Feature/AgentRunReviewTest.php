<?php

namespace Tests\Feature;

use App\Actions\AgentRuns\ApplyAgentRunAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\AgentAutonomy;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use App\Enums\BoardRole;
use App\Jobs\Agents\ExecuteAgentRunJob;
use App\Models\AgentRun;
use App\Models\AgentRunAction;
use App\Models\AiProviderConnection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentRunReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_approve_proposed_action(): void
    {
        [$manager, $run, $task] = $this->runFixture();
        $action = $this->actionFor($run, AgentRunActionType::UpdateTaskFields, [
            'fields' => [
                'title' => 'Approved task title',
            ],
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.actions.approve', [$run, $action]))
            ->assertOk()
            ->assertJsonPath('agent_run.status', AgentRunStatus::Completed->value)
            ->assertJsonPath('agent_run.actions.0.status', AgentRunActionStatus::Applied->value);

        $action->refresh();
        $task->refresh();

        $this->assertSame('Approved task title', $task->title);
        $this->assertSame(AgentRunActionStatus::Applied, $action->status);
        $this->assertSame($manager->id, $action->approved_by);
        $this->assertNotNull($action->approved_at);
        $this->assertNotNull($action->applied_at);
    }

    public function test_field_update_approval_ignores_null_placeholders(): void
    {
        [$manager, $run, $task] = $this->runFixture();
        $action = $this->actionFor($run, AgentRunActionType::UpdateTaskFields, [
            'fields' => [
                'title' => null,
                'description' => null,
                'tags' => ['backend', '', 'Backend'],
                'priority' => null,
                'deadline_at' => null,
            ],
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.actions.approve', [$run, $action]))
            ->assertOk()
            ->assertJsonPath('agent_run.actions.0.status', AgentRunActionStatus::Applied->value);

        $task->refresh();

        $this->assertSame('Write release checklist', $task->title);
        $this->assertSame(['backend'], $task->tags);
    }

    public function test_manager_can_reject_proposed_action(): void
    {
        [$manager, $run, $task] = $this->runFixture();
        $action = $this->actionFor($run, AgentRunActionType::UpdateTaskFields, [
            'fields' => [
                'title' => 'Rejected task title',
            ],
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.actions.reject', [$run, $action]))
            ->assertOk()
            ->assertJsonPath('agent_run.status', AgentRunStatus::Completed->value)
            ->assertJsonPath('agent_run.actions.0.status', AgentRunActionStatus::Rejected->value);

        $action->refresh();
        $task->refresh();

        $this->assertSame('Write release checklist', $task->title);
        $this->assertSame(AgentRunActionStatus::Rejected, $action->status);
        $this->assertSame($manager->id, $action->approved_by);
        $this->assertNotNull($action->rejected_at);
    }

    public function test_applier_leaves_non_proposed_actions_unchanged(): void
    {
        [$manager, $run, $task] = $this->runFixture(status: AgentRunStatus::Completed);
        $action = $this->actionFor($run, AgentRunActionType::UpdateTaskFields, [
            'fields' => [
                'title' => 'Should not apply',
            ],
        ]);
        $action->forceFill([
            'status' => AgentRunActionStatus::Suggested,
        ])->save();

        app(ApplyAgentRunAction::class)->execute($action, $manager);

        $action->refresh();
        $task->refresh();

        $this->assertSame(AgentRunActionStatus::Suggested, $action->status);
        $this->assertNull($action->applied_at);
        $this->assertSame('Write release checklist', $task->title);
    }

    public function test_manager_can_approve_all_proposed_actions(): void
    {
        [$manager, $run, $task] = $this->runFixture();
        $this->actionFor($run, AgentRunActionType::AddComment, [
            'comment' => 'Looks ready after the title change.',
        ]);
        $this->actionFor($run, AgentRunActionType::UpdateTaskFields, [
            'fields' => [
                'title' => 'Approved by batch',
            ],
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.approve-all', $run))
            ->assertOk()
            ->assertJsonPath('agent_run.status', AgentRunStatus::Completed->value);

        $this->assertSame('Approved by batch', $task->fresh()->title);
        $this->assertSame(0, $run->actions()
            ->where('status', AgentRunActionStatus::Proposed->value)
            ->count());
        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'content' => 'Looks ready after the title change.',
        ]);
    }

    public function test_only_run_manager_can_review_action(): void
    {
        [$manager, $run] = $this->runFixture();
        $otherManager = User::factory()->create();
        $action = $this->actionFor($run, AgentRunActionType::AddComment, [
            'comment' => 'Nope.',
        ]);

        $this->actingAs($otherManager)
            ->postJson(route('agent-runs.actions.approve', [$run, $action]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->postJson(route('agent-runs.actions.approve', [$run, $action]))
            ->assertOk();
    }

    public function test_manager_can_retry_failed_run(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::Failed);
        $run->forceFill([
            'error_code' => 'provider_error',
            'error_message' => 'Provider failed.',
            'failed_at' => now(),
        ])->save();
        $staleAction = $this->actionFor($run, AgentRunActionType::AddComment, [
            'comment' => 'Stale suggestion from a failed attempt.',
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertOk()
            ->assertJsonPath('agent_run.status', AgentRunStatus::Queued->value)
            ->assertJsonPath('agent_run.error.code', null);

        $run->refresh();

        $this->assertNull($run->failed_at);
        $this->assertDatabaseMissing('agent_run_actions', [
            'id' => $staleAction->id,
        ]);
        Queue::assertPushed(
            ExecuteAgentRunJob::class,
            fn (ExecuteAgentRunJob $job): bool => $job->run->is($run)
                && $job->queue === 'agents',
        );
    }

    public function test_retry_requires_no_other_active_task_run(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::Failed);
        AgentRun::query()->create([
            'board_id' => $run->board_id,
            'task_id' => $run->task_id,
            'agent_id' => $run->agent_id,
            'manager_id' => $run->manager_id,
            'provider_connection_id' => $run->provider_connection_id,
            'provider' => $run->provider,
            'model' => $run->model,
            'autonomy' => $run->autonomy,
            'status' => AgentRunStatus::Queued,
            'context_snapshot' => [],
        ]);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['task']);

        $this->assertSame(AgentRunStatus::Failed, $run->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_requires_verified_provider_connection(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::Failed);
        $run->providerConnection->forceFill([
            'verified_at' => null,
        ])->save();

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent']);

        $this->assertSame(AgentRunStatus::Failed, $run->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_requires_agent_to_remain_assigned_to_task(): void
    {
        Queue::fake();

        [$manager, $run, $task] = $this->runFixture(status: AgentRunStatus::Failed);
        $task->users()->detach($run->agent_id);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent']);

        $this->assertSame(AgentRunStatus::Failed, $run->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_requires_agent_to_remain_active(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::Failed);
        $run->agent->forceFill([
            'agent_archived_at' => now(),
        ])->save();

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent']);

        $this->assertSame(AgentRunStatus::Failed, $run->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_requires_agent_to_remain_board_member(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::Failed);
        $run->board->members()->detach($run->agent_id);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent']);

        $this->assertSame(AgentRunStatus::Failed, $run->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_retry_requires_failed_run(): void
    {
        Queue::fake();

        [$manager, $run] = $this->runFixture(status: AgentRunStatus::AwaitingApproval);

        $this->actingAs($manager)
            ->postJson(route('agent-runs.retry', $run))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['run']);

        Queue::assertNothingPushed();
    }

    /**
     * @return array{0: User, 1: AgentRun, 2: Task}
     */
    private function runFixture(AgentRunStatus $status = AgentRunStatus::AwaitingApproval): array
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
            'agent_autonomy' => AgentAutonomy::Approval,
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

        $run = AgentRun::query()->create([
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'provider_connection_id' => $connection->id,
            'provider' => AiProvider::OpenAI,
            'model' => AiProviderConnection::DEFAULT_MODEL,
            'autonomy' => AgentAutonomy::Approval,
            'status' => $status,
            'summary' => 'Review these actions.',
            'rationale' => 'The manager should decide.',
            'context_snapshot' => [],
        ]);

        return [$manager, $run, $task];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function actionFor(
        AgentRun $run,
        AgentRunActionType $type,
        array $payload,
    ): AgentRunAction {
        return $run->actions()->create([
            'type' => $type,
            'status' => AgentRunActionStatus::Proposed,
            'payload' => $payload,
        ]);
    }
}
