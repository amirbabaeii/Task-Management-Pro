<?php

namespace Tests\Feature;

use App\Enums\AgentAutonomy;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use App\Models\AgentRun;
use App\Models\AiProviderConnection;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunOperationalTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_removes_old_context_snapshots_only(): void
    {
        $oldRun = $this->runWithContext(now()->subDays(45));
        $freshRun = $this->runWithContext(now()->subDays(5));
        $refreshedOldRun = $this->runWithContext(now()->subDays(45));
        $refreshedOldRun->forceFill([
            'updated_at' => now()->subDays(2),
        ])->save();
        $oldQueuedRun = $this->runWithContext(now()->subDays(45), AgentRunStatus::Queued);

        $this->artisan('agents:prune-run-payloads', ['--days' => 30])
            ->expectsOutput('Pruned 1 agent run context snapshot(s).')
            ->assertSuccessful();

        $this->assertNull($oldRun->fresh()->context_snapshot);
        $this->assertSame(
            'Fresh task',
            $freshRun->fresh()->context_snapshot['task']['title'],
        );
        $this->assertSame(
            'Fresh task',
            $refreshedOldRun->fresh()->context_snapshot['task']['title'],
        );
        $this->assertSame(
            'Fresh task',
            $oldQueuedRun->fresh()->context_snapshot['task']['title'],
        );
        $this->assertDatabaseHas('agent_runs', [
            'id' => $oldRun->id,
            'summary' => 'Retain audit summary',
        ]);
    }

    private function runWithContext(
        $createdAt,
        AgentRunStatus $status = AgentRunStatus::Completed,
    ): AgentRun {
        $manager = User::factory()->create();
        $agent = User::factory()->create([
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
        ]);
        $board = Board::factory()->create([
            'user_id' => $manager->id,
        ]);
        $task = Task::factory()->create([
            'title' => 'Fresh task',
        ]);
        $connection = $manager->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-manager-secret',
            'default_model' => AiProviderConnection::DEFAULT_MODEL,
            'verified_at' => now(),
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
            'summary' => 'Retain audit summary',
            'context_snapshot' => [
                'task' => [
                    'title' => $task->title,
                ],
            ],
        ]);

        $run->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $run;
    }
}
