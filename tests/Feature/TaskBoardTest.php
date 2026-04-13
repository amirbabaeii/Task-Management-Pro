<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_task_from_the_board(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Ship Inertia task form',
            'description' => 'Allow task creation directly from the board.',
            'status' => 'pending',
            'priority' => 'medium',
            'deadline_at' => '2026-04-15',
        ]);

        $response
            ->assertRedirect(route('tasks.board'))
            ->assertSessionHasNoErrors();

        $task = Task::query()
            ->where('title', 'Ship Inertia task form')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame('pending', $task->status);
        $this->assertSame('medium', $task->priority);
        $this->assertSame('2026-04-15', $task->deadline_at?->toDateString());

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
    }

    public function test_task_board_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('tasks.board'))
            ->actingAs($user)
            ->post(route('tasks.store'), [
                'title' => '   ',
                'status' => 'blocked',
                'priority' => 'urgent',
            ]);

        $response
            ->assertRedirect(route('tasks.board'))
            ->assertSessionHasErrors(['title', 'status', 'priority']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_assignee_can_update_task_status_from_the_board(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status', $task),
            ['status' => 'completed'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('task.id', $task->id)
            ->assertJsonPath('task.status', 'completed');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_assignee_can_update_task_details_from_the_board(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'status' => 'pending',
            'priority' => 'low',
            'progress' => 10,
            'deadline_at' => '2026-04-12',
        ]);

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->from(route('tasks.board'))
            ->actingAs($user)
            ->patch(route('tasks.update', $task), [
                'title' => 'Updated task title',
                'description' => 'Updated description',
                'status' => 'in-progress',
                'priority' => 'high',
                'progress' => 70,
                'deadline_at' => '2026-04-20',
            ]);

        $response
            ->assertRedirect(route('tasks.board'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated task title',
            'description' => 'Updated description',
            'status' => 'in-progress',
            'priority' => 'high',
            'progress' => 70,
        ]);

        $this->assertSame(
            '2026-04-20',
            $task->fresh()->deadline_at?->toDateString(),
        );
    }

    public function test_authenticated_user_can_update_a_board_column_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status-labels.update', 'pending'),
            ['label' => 'Backlog'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('label', 'Backlog')
            ->assertJsonPath('status_labels.pending', 'Backlog');

        $this->assertDatabaseHas('board_columns', [
            'user_id' => $user->id,
            'status' => 'pending',
            'label' => 'Backlog',
        ]);
    }

    public function test_authenticated_user_can_add_a_board_column(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.columns.store'), [
            'label' => 'Review',
        ]);

        $response
            ->assertRedirect(route('tasks.board'))
            ->assertSessionHasNoErrors();

        $column = BoardColumn::query()
            ->where('user_id', $user->id)
            ->where('label', 'Review')
            ->first();

        $this->assertNotNull($column);
        $this->assertSame(4, $column->position);
        $this->assertStringStartsWith('column-', $column->status);
    }

    public function test_authenticated_user_can_reorder_board_columns(): void
    {
        $user = User::factory()->create();

        BoardColumn::ensureDefaultsForUser($user);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.columns.reorder', 'completed'),
            ['before_status' => 'pending'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('statuses.0', 'completed')
            ->assertJsonPath('statuses.1', 'pending')
            ->assertJsonPath('statuses.2', 'in-progress');

        $this->assertDatabaseHas('board_columns', [
            'user_id' => $user->id,
            'status' => 'completed',
            'position' => 1,
        ]);
        $this->assertDatabaseHas('board_columns', [
            'user_id' => $user->id,
            'status' => 'pending',
            'position' => 2,
        ]);
        $this->assertDatabaseHas('board_columns', [
            'user_id' => $user->id,
            'status' => 'in-progress',
            'position' => 3,
        ]);
    }

    public function test_assignee_can_move_a_task_to_a_custom_board_column(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        BoardColumn::ensureDefaultsForUser($user);
        $column = BoardColumn::query()->create([
            'user_id' => $user->id,
            'status' => 'column-review',
            'label' => 'Review',
            'position' => 4,
        ]);

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status', $task),
            ['status' => $column->status],
        );

        $response
            ->assertOk()
            ->assertJsonPath('task.id', $task->id)
            ->assertJsonPath('task.status', $column->status);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => $column->status,
        ]);
    }

    public function test_assignee_can_reorder_tasks_within_the_same_column(): void
    {
        $user = User::factory()->create();
        $firstTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $secondTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $thirdTask = Task::factory()->create([
            'status' => 'pending',
        ]);

        $this->attachAssignee($user, $firstTask, 1);
        $this->attachAssignee($user, $secondTask, 2);
        $this->attachAssignee($user, $thirdTask, 3);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.reorder', $thirdTask),
            [
                'status' => 'pending',
                'before_id' => $firstTask->id,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('task.id', $thirdTask->id)
            ->assertJsonPath('task.status', 'pending')
            ->assertJsonPath('task.sort_order', 1);

        $this->assertDatabaseHas('task_user', [
            'task_id' => $thirdTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $firstTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $secondTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 3,
        ]);
    }

    public function test_assignee_can_move_a_task_to_another_column_and_place_it_before_another_task(): void
    {
        $user = User::factory()->create();
        $pendingTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $inProgressTask = Task::factory()->create([
            'status' => 'in-progress',
        ]);
        $anotherInProgressTask = Task::factory()->create([
            'status' => 'in-progress',
        ]);

        $this->attachAssignee($user, $pendingTask, 1);
        $this->attachAssignee($user, $inProgressTask, 1);
        $this->attachAssignee($user, $anotherInProgressTask, 2);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.reorder', $pendingTask),
            [
                'status' => 'in-progress',
                'before_id' => $inProgressTask->id,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('task.id', $pendingTask->id)
            ->assertJsonPath('task.status', 'in-progress')
            ->assertJsonPath('task.sort_order', 1);

        $this->assertDatabaseHas('tasks', [
            'id' => $pendingTask->id,
            'status' => 'in-progress',
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $pendingTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $inProgressTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $anotherInProgressTask->id,
            'user_id' => $user->id,
            'role' => 'assignee',
            'sort_order' => 3,
        ]);
    }

    public function test_task_board_update_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->from(route('tasks.board'))
            ->actingAs($user)
            ->patch(route('tasks.update', $task), [
                'title' => '   ',
                'description' => str_repeat('a', 1001),
                'status' => 'blocked',
                'priority' => 'urgent',
                'progress' => 120,
            ]);

        $response
            ->assertRedirect(route('tasks.board'))
            ->assertSessionHasErrors([
                'title',
                'description',
                'status',
                'priority',
                'progress',
            ]);
    }

    public function test_board_column_labels_require_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status-labels.update', 'blocked'),
            ['label' => '   '],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'label']);
    }

    private function attachAssignee(User $user, Task $task, int $sortOrder): void
    {
        $task->users()->attach($user->id, [
            'role' => 'assignee',
            'sort_order' => $sortOrder,
        ]);
    }
}
