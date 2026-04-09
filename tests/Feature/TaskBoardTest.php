<?php

namespace Tests\Feature;

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

    public function test_assignee_can_update_task_progress_from_the_board(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'progress' => 15,
        ]);

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.progress', $task),
            ['progress' => 65],
        );

        $response
            ->assertOk()
            ->assertJsonPath('task.id', $task->id)
            ->assertJsonPath('task.progress', 65);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'progress' => 65,
        ]);
    }

    public function test_task_progress_update_requires_a_valid_percentage(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'progress' => 20,
        ]);

        $task->users()->attach($user->id, [
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.progress', $task),
            ['progress' => 120],
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['progress']);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'progress' => 20,
        ]);
    }
}
