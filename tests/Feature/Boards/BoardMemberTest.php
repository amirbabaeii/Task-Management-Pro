<?php

namespace Tests\Feature\Boards;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_non_member_gets_404_on_the_board(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);

        $this->actingAs($stranger)
            ->get(route('tasks.board', ['board' => $board]))
            ->assertNotFound();
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }
}
