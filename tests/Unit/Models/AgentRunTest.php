<?php

namespace Tests\Unit\Models;

use App\Enums\AgentAutonomy;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use App\Enums\AgentRunStatus;
use App\Enums\AiProvider;
use App\Models\AgentRun;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_and_action_cast_execution_state(): void
    {
        $manager = User::factory()->create();
        $agent = User::factory()->create([
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
        ]);
        $board = Board::factory()->create(['user_id' => $manager->id]);
        $task = Task::factory()->create();

        $run = AgentRun::create([
            'board_id' => $board->id,
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'manager_id' => $manager->id,
            'provider' => AiProvider::OpenAI,
            'model' => 'gpt-5.5',
            'autonomy' => AgentAutonomy::Approval,
            'status' => AgentRunStatus::Queued,
            'context_snapshot' => [
                'task' => ['title' => $task->title],
            ],
        ]);
        $action = $run->actions()->create([
            'type' => AgentRunActionType::AddComment,
            'status' => AgentRunActionStatus::Proposed,
            'payload' => ['comment' => 'Ready for review.'],
        ]);

        $this->assertSame(AgentRunStatus::Queued, $run->status);
        $this->assertTrue($run->status->isActive());
        $this->assertSame(AgentAutonomy::Approval, $run->autonomy);
        $this->assertSame(AiProvider::OpenAI, $run->provider);
        $this->assertSame($task->title, $run->context_snapshot['task']['title']);
        $this->assertSame(AgentRunActionType::AddComment, $action->type);
        $this->assertTrue($action->status->isPendingApproval());
        $this->assertTrue($run->agent->is($agent));
        $this->assertTrue($run->manager->is($manager));
        $this->assertTrue($task->agentRuns->first()->is($run));
    }
}
