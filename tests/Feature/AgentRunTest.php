<?php

namespace Tests\Feature;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\AgentAutonomy;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use App\Enums\BoardRole;
use App\Models\AgentRun;
use App\Models\AiProviderConnection;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_start_queued_run_for_assigned_agent(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture();

        $this->actingAs($manager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
                'autonomy' => AgentAutonomy::Advisory->value,
            ])
            ->assertCreated()
            ->assertJsonPath('agent_run.status', AgentRunStatus::Queued->value)
            ->assertJsonPath('agent_run.autonomy', AgentAutonomy::Advisory->value)
            ->assertJsonPath('agent_run.agent.id', $agent->id)
            ->assertJsonPath('agent_run.manager.id', $manager->id);

        $this->assertDatabaseHas('agent_runs', [
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'status' => AgentRunStatus::Queued->value,
            'autonomy' => AgentAutonomy::Advisory->value,
        ]);

        $run = AgentRun::query()->firstOrFail();

        $this->assertSame($task->title, $run->context_snapshot['task']['title']);
        $this->assertSame($agent->name, $run->context_snapshot['agent']['name']);
    }

    public function test_requested_autonomy_cannot_exceed_agent_default(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture([
            'agent_autonomy' => AgentAutonomy::Approval,
        ]);

        $this->actingAs($manager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
                'autonomy' => AgentAutonomy::Automatic->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['autonomy']);
    }

    public function test_only_agent_manager_can_start_run(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture();
        $otherManager = User::factory()->create();

        $board->members()->attach($otherManager->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($otherManager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
            ])
            ->assertNotFound();
    }

    public function test_agent_must_be_assigned_to_task(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture(assignAgentToTask: false);

        $this->actingAs($manager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id']);
    }

    public function test_agent_requires_verified_provider_connection(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture(verifiedConnection: false);

        $this->actingAs($manager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agent_id']);
    }

    public function test_only_one_active_run_is_allowed_per_task(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture();

        AgentRun::query()->create([
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'provider_connection_id' => $agent->agent_provider_connection_id,
            'provider' => AiProvider::OpenAI,
            'model' => AiProviderConnection::DEFAULT_MODEL,
            'autonomy' => AgentAutonomy::Approval,
            'status' => AgentRunStatus::Running,
            'context_snapshot' => [],
        ]);

        $this->actingAs($manager)
            ->postJson(route('tasks.agent-runs.store', [$board, $task]), [
                'agent_id' => $agent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['task']);
    }

    public function test_manager_can_list_task_runs(): void
    {
        [$manager, $board, $task, $agent] = $this->fixture();

        $run = AgentRun::query()->create([
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'provider_connection_id' => $agent->agent_provider_connection_id,
            'provider' => AiProvider::OpenAI,
            'model' => AiProviderConnection::DEFAULT_MODEL,
            'autonomy' => AgentAutonomy::Approval,
            'status' => AgentRunStatus::Completed,
            'summary' => 'Suggested next step.',
            'context_snapshot' => [],
        ]);

        $this->actingAs($manager)
            ->getJson(route('tasks.agent-runs.index', [$board, $task]))
            ->assertOk()
            ->assertJsonPath('agent_runs.0.id', $run->id)
            ->assertJsonPath('agent_runs.0.summary', 'Suggested next step.')
            ->assertJsonPath('agent_runs.0.agent.id', $agent->id);
    }

    /**
     * @param  array<string, mixed>  $agentAttributes
     * @return array{0: User, 1: Board, 2: Task, 3: User}
     */
    private function fixture(
        array $agentAttributes = [],
        bool $assignAgentToTask = true,
        bool $verifiedConnection = true,
    ): array {
        $manager = User::factory()->create();
        $board = $this->boardFor($manager);
        $connection = $manager->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-manager-secret',
            'default_model' => AiProviderConnection::DEFAULT_MODEL,
            'verified_at' => $verifiedConnection ? now() : null,
        ]);

        $agent = User::factory()->create(array_merge([
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
            'agent_provider_connection_id' => $connection->id,
            'agent_autonomy' => AgentAutonomy::Approval,
        ], $agentAttributes));

        $board->members()->attach($agent->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create([
            'title' => 'Write release checklist',
        ]);

        $task->users()->attach($manager->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        if ($assignAgentToTask) {
            $task->users()->attach($agent->id, [
                'board_id' => $board->id,
                'role' => 'assignee',
                'sort_order' => 2,
            ]);
        }

        return [$manager, $board, $task, $agent->fresh('agentProviderConnection')];
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }
}
