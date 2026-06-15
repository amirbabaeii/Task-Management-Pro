<?php

namespace Tests\Unit\Services\Ai;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\TaskActivityKind;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AgentTaskContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTaskContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_contains_bounded_task_and_agent_history(): void
    {
        $manager = User::factory()->create();
        $board = app(EnsureUserHasDefaultBoardAction::class)
            ->execute($manager);
        $agent = User::factory()->create([
            'name' => 'Planner Agent',
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
            'agent_profile' => 'Breaks work into practical steps.',
            'agent_personality' => 'Concise and careful.',
            'agent_skills' => ['planning', 'testing'],
        ]);
        $board->members()->attach($agent->id, [
            'role' => 'collaborator',
            'joined_at' => now(),
        ]);
        $task = Task::factory()->create([
            'title' => 'Ship agent runs',
            'status' => 'pending',
            'priority' => 'high',
        ]);
        $task->users()->attach($agent->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
        $task->checklistItems()->create([
            'title' => 'Add migrations',
            'position' => 1,
        ]);

        foreach (range(1, 25) as $index) {
            $task->comments()->create([
                'user_id' => $manager->id,
                'content' => "Comment {$index}",
                'created_at' => now()->addSeconds($index),
            ]);
        }

        foreach (range(1, 35) as $index) {
            $task->activities()->create([
                'user_id' => $manager->id,
                'kind' => TaskActivityKind::CommentAdded,
                'payload' => ['index' => $index],
                'created_at' => now()->addMinutes($index),
            ]);
        }

        $context = app(AgentTaskContextBuilder::class)
            ->build($board, $task, $agent);

        $this->assertSame('Ship agent runs', $context['task']['title']);
        $this->assertSame('high', $context['task']['priority']);
        $this->assertSame('Planner Agent', $context['agent']['name']);
        $this->assertSame(['planning', 'testing'], $context['agent']['skills']);
        $this->assertCount(20, $context['task']['comments']);
        $this->assertSame(
            'Comment 6',
            $context['task']['comments'][0]['content'],
        );
        $this->assertSame(
            'Comment 25',
            $context['task']['comments'][19]['content'],
        );
        $this->assertCount(30, $context['task']['recent_activity']);
        $this->assertSame(
            6,
            $context['task']['recent_activity'][0]['payload']['index'],
        );
        $this->assertSame(
            35,
            $context['task']['recent_activity'][29]['payload']['index'],
        );
        $this->assertArrayNotHasKey('api_key', $context['agent']);
    }
}
