<?php

namespace Tests\Feature\Boards;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Notifications\BoardMemberAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BoardMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_membership_row_is_seeded_when_a_board_is_created(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);

        $this->assertDatabaseHas('board_members', [
            'board_id' => $board->id,
            'user_id' => $owner->id,
            'role' => BoardRole::Owner->value,
        ]);
    }

    public function test_owner_can_invite_an_existing_user_by_email(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'collab@example.com']);
        $board = $this->boardFor($owner);

        $response = $this->actingAs($owner)->postJson(
            route('boards.members.store', ['board' => $board]),
            ['email' => 'collab@example.com'],
        );

        $response->assertCreated();

        $this->assertDatabaseHas('board_members', [
            'board_id' => $board->id,
            'user_id' => $invitee->id,
            'role' => BoardRole::Collaborator->value,
        ]);

        $notification = $invitee->notifications()
            ->where('type', BoardMemberAddedNotification::class)
            ->firstOrFail();

        $this->assertSame('board_member_added', $notification->data['kind']);
        $this->assertSame($board->id, $notification->data['board']['id']);
        $this->assertSame($owner->id, $notification->data['invited_by']['id']);
    }

    public function test_members_endpoint_lists_available_managed_agents(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);
        $availableAgent = User::factory()->create([
            'name' => 'Scout Agent',
            'email' => 'scout.agent@example.com',
            'is_agent' => true,
            'agent_manager_id' => $owner->id,
            'agent_title' => 'Research Agent',
        ]);
        $archivedAgent = User::factory()->create([
            'email' => 'archived.agent@example.com',
            'is_agent' => true,
            'agent_manager_id' => $owner->id,
            'agent_archived_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('boards.members.index', ['board' => $board]));

        $response
            ->assertOk()
            ->assertJsonPath('available_agents.0.id', $availableAgent->id)
            ->assertJsonMissing([
                'id' => $archivedAgent->id,
            ]);
    }

    public function test_inviting_available_agent_removes_them_from_picker(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);
        $agent = User::factory()->create([
            'email' => 'planner.agent@example.com',
            'is_agent' => true,
            'agent_manager_id' => $owner->id,
            'agent_title' => 'Planning Agent',
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('boards.members.store', ['board' => $board]),
            ['email' => $agent->email],
        );

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'id' => $agent->id,
                'is_agent' => true,
                'agent_title' => 'Planning Agent',
            ])
            ->assertJsonPath('available_agents', []);
    }

    public function test_invite_rejects_archived_agent(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);
        $agent = User::factory()->create([
            'email' => 'retired.agent@example.com',
            'is_agent' => true,
            'agent_manager_id' => $owner->id,
            'agent_archived_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('boards.members.store', ['board' => $board]), [
                'email' => $agent->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_rejects_unknown_email(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);

        $response = $this->actingAs($owner)->postJson(
            route('boards.members.store', ['board' => $board]),
            ['email' => 'ghost@example.com'],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_rejects_a_user_already_on_the_board(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);

        $response = $this->actingAs($owner)->postJson(
            route('boards.members.store', ['board' => $board]),
            ['email' => $owner->email],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_collaborator_cannot_invite_another_user(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($collab)->postJson(
            route('boards.members.store', ['board' => $board]),
            ['email' => $stranger->email],
        );

        $response->assertNotFound();
    }

    public function test_owner_can_remove_a_collaborator(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($owner)->deleteJson(
            route('boards.members.destroy', ['board' => $board, 'user' => $collab]),
        );

        $response->assertOk();
        $this->assertDatabaseMissing('board_members', [
            'board_id' => $board->id,
            'user_id' => $collab->id,
        ]);
    }

    public function test_removing_a_member_detaches_their_task_assignments_on_that_board(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create(['status' => 'pending']);
        $task->users()->attach($collab->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('boards.members.destroy', ['board' => $board, 'user' => $collab]))
            ->assertOk();

        $this->assertDatabaseMissing('task_user', [
            'board_id' => $board->id,
            'user_id' => $collab->id,
        ]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_collaborator_can_view_the_board(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($collab)
            ->get(route('tasks.board', ['board' => $board]))
            ->assertOk();
    }

    public function test_collaborator_sees_board_columns_and_existing_tasks(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create([
            'title' => 'Review shared roadmap',
            'status' => 'pending',
        ]);
        $task->users()->attach($owner->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        $this->actingAs($collab)
            ->get(route('tasks.board', ['board' => $board]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Board')
                ->has('statuses', 3)
                ->where('statuses.0', 'pending')
                ->has('tasks', 1)
                ->where('tasks.0.id', $task->id)
                ->where('tasks.0.title', 'Review shared roadmap')
                ->where('tasks.0.assignees.0.id', $owner->id));
    }

    public function test_collaborator_can_update_existing_board_tasks(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create([
            'title' => 'Draft release notes',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $task->users()->attach($owner->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        $this->actingAs($collab)
            ->patch(route('tasks.update', ['board' => $board, 'task' => $task]), [
                'title' => 'Draft and review release notes',
                'description' => 'Include rollout risks and support notes.',
                'status' => 'in-progress',
                'priority' => 'high',
                'progress' => 50,
                'assignee_ids' => [$owner->id],
            ])
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Draft and review release notes',
            'status' => 'in-progress',
            'priority' => 'high',
            'progress' => 50,
        ]);
    }

    public function test_board_page_exposes_owner_state_and_accessible_boards(): void
    {
        $owner = User::factory()->create();
        $collaboratorOwner = User::factory()->create();
        $ownedBoard = $this->boardFor($owner);
        $sharedBoard = $this->boardFor($collaboratorOwner);
        $ownedBoard->update(['name' => 'Owned Delivery']);
        $sharedBoard->update(['name' => 'Shared Ops']);

        $sharedBoard->members()->attach($owner->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('tasks.board', ['board' => $ownedBoard]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Board')
                ->where('currentBoard.id', $ownedBoard->id)
                ->where('currentBoard.role', BoardRole::Owner->value)
                ->where('currentBoard.is_owner', true)
                ->has('boards', 2)
                ->where('boards.0.id', $ownedBoard->id)
                ->where('boards.0.is_owner', true)
                ->where('boards.1.id', $sharedBoard->id)
                ->where('boards.1.role', BoardRole::Collaborator->value)
                ->where('boards.1.is_owner', false));
    }

    public function test_non_member_gets_404_on_the_board(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);

        $this->actingAs($stranger)
            ->get(route('tasks.board', ['board' => $board]))
            ->assertNotFound();
    }

    public function test_owner_can_create_a_task_assigned_to_a_collaborator(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($owner)->post(
            route('tasks.store', ['board' => $board]),
            [
                'title' => 'Pair on the migration',
                'status' => 'pending',
                'priority' => 'medium',
                'assignee_ids' => [$collab->id],
            ],
        );

        $response->assertRedirect()->assertSessionHasNoErrors();

        $task = Task::query()->where('title', 'Pair on the migration')->first();
        $this->assertNotNull($task);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $collab->id,
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);
        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_creating_a_task_without_assignee_ids_falls_back_to_creator(): void
    {
        $owner = User::factory()->create();
        $board = $this->boardFor($owner);

        $this->actingAs($owner)
            ->post(route('tasks.store', ['board' => $board]), [
                'title' => 'Solo task',
                'status' => 'pending',
                'priority' => 'medium',
            ])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Solo task')->first();

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'role' => 'assignee',
        ]);
    }

    public function test_cannot_assign_a_user_who_is_not_a_board_member(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);

        $response = $this->actingAs($owner)
            ->from(route('tasks.board', ['board' => $board]))
            ->post(route('tasks.store', ['board' => $board]), [
                'title' => 'Bad assignment',
                'status' => 'pending',
                'priority' => 'medium',
                'assignee_ids' => [$stranger->id],
            ]);

        $response->assertSessionHasErrors(['assignee_ids.0']);
    }

    public function test_updating_a_task_replaces_the_assignee_set(): void
    {
        $owner = User::factory()->create();
        $collab = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collab->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create(['status' => 'pending']);
        $task->users()->attach($owner->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->patch(route('tasks.update', ['board' => $board, 'task' => $task]), [
                'title' => $task->title,
                'description' => $task->description,
                'status' => 'pending',
                'priority' => 'medium',
                'progress' => 0,
                'assignee_ids' => [$collab->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $collab->id,
            'role' => 'assignee',
        ]);
        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $owner->id,
        ]);
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }
}
