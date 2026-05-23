<?php

namespace Tests\Feature;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_archive_restore_and_delete_managed_agent(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->get(route('agents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agents/Index')
                ->has('agents', 0)
                ->has('archivedAgents', 0));

        $createResponse = $this->actingAs($manager)->postJson(route('agents.store'), [
            'name' => 'Noah Analyst',
            'email' => 'noah.agent@example.com',
            'agent_title' => 'Planning Agent',
            'agent_profile' => 'Keeps plans tidy and flags missing work.',
            'agent_personality' => 'Calm, concise, and practical.',
            'agent_skills' => ['planning', 'QA', 'planning'],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('agent.name', 'Noah Analyst')
            ->assertJsonPath('agent.skills.0', 'planning')
            ->assertJsonPath('agent.skills.1', 'QA');

        $agent = User::query()
            ->where('email', 'noah.agent@example.com')
            ->firstOrFail();

        $this->assertTrue($agent->is_agent);
        $this->assertSame($manager->id, $agent->agent_manager_id);
        $this->assertTrue(Hash::check('not-the-password', $agent->password) === false);
        $this->assertDatabaseMissing('boards', [
            'user_id' => $agent->id,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('agents.update', ['agent' => $agent]), [
                'name' => 'Noah QA',
                'email' => 'noah.qa@example.com',
                'agent_title' => 'QA Agent',
                'agent_profile' => 'Reviews edge cases.',
                'agent_personality' => 'Direct and careful.',
                'agent_skills' => ['testing'],
            ])
            ->assertOk()
            ->assertJsonPath('agent.name', 'Noah QA')
            ->assertJsonPath('agent.skills.0', 'testing');

        $this->assertSame('QA Agent', $agent->fresh()->agent_title);

        $this->actingAs($manager)
            ->patchJson(route('agents.archive', ['agent' => $agent]))
            ->assertOk()
            ->assertJsonPath('agent.id', $agent->id);

        $this->assertNotNull($agent->fresh()->agent_archived_at);

        $this->actingAs($manager)
            ->get(route('agents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agents', 0)
                ->has('archivedAgents', 1)
                ->where('archivedAgents.0.id', $agent->id));

        $this->actingAs($manager)
            ->patchJson(route('agents.restore', ['agent' => $agent]))
            ->assertOk()
            ->assertJsonPath('agent.archived_at', null);

        $this->assertNull($agent->fresh()->agent_archived_at);

        $this->actingAs($manager)
            ->deleteJson(route('agents.destroy', ['agent' => $agent]))
            ->assertOk()
            ->assertJsonPath('id', $agent->id);

        $this->assertDatabaseMissing('users', [
            'id' => $agent->id,
        ]);
    }

    public function test_user_cannot_manage_another_users_agent(): void
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->create();
        $agent = User::factory()->create([
            'is_agent' => true,
            'agent_manager_id' => $otherManager->id,
        ]);

        $this->actingAs($manager)
            ->patchJson(route('agents.update', ['agent' => $agent]), [
                'name' => 'Borrowed Agent',
                'email' => $agent->email,
                'agent_skills' => [],
            ])
            ->assertNotFound();
    }

    public function test_agent_index_includes_workload_counts(): void
    {
        $manager = User::factory()->create();
        $board = $this->boardFor($manager);
        $agent = User::factory()->create([
            'name' => 'Scout Agent',
            'email' => 'scout.workload@example.com',
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
        ]);

        $board->members()->attach($agent->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $activeTask = Task::factory()->create([
            'title' => 'Research options',
            'deadline_at' => now()->addDay(),
            'archived_at' => null,
        ]);
        $overdueTask = Task::factory()->create([
            'title' => 'Audit backlog',
            'deadline_at' => now()->subDay(),
            'archived_at' => null,
        ]);
        $archivedTask = Task::factory()->create([
            'title' => 'Old import review',
            'deadline_at' => now()->subDays(2),
            'archived_at' => now(),
        ]);

        foreach ([$activeTask, $overdueTask, $archivedTask] as $index => $task) {
            $task->users()->attach($agent->id, [
                'board_id' => $board->id,
                'role' => 'assignee',
                'sort_order' => $index + 1,
            ]);
        }

        $this->actingAs($manager)
            ->get(route('agents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('agents.0.id', $agent->id)
                ->where('agents.0.workload.boards', 1)
                ->where('agents.0.workload.active_tasks', 2)
                ->where('agents.0.workload.overdue_tasks', 1));
    }

    public function test_managed_agent_can_join_board_and_receive_tasks(): void
    {
        $manager = User::factory()->create();
        $board = $this->boardFor($manager);
        $agent = User::factory()->create([
            'name' => 'Scout Agent',
            'email' => 'scout.agent@example.com',
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
            'agent_title' => 'Research Agent',
        ]);

        $this->actingAs($manager)
            ->postJson(route('boards.members.store', ['board' => $board]), [
                'email' => $agent->email,
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'id' => $agent->id,
                'is_agent' => true,
                'agent_title' => 'Research Agent',
            ]);

        $this->actingAs($manager)
            ->post(route('tasks.store', ['board' => $board]), [
                'title' => 'Research import options',
                'description' => 'Compare the available import approaches.',
                'status' => 'pending',
                'priority' => 'medium',
                'tags' => [],
                'deadline_at' => null,
                'assignee_ids' => [$agent->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_user', [
            'user_id' => $agent->id,
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }
}
