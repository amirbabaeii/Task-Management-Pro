<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_task_from_the_board(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->post(route('tasks.store', ['board' => $board]), [
            'title' => 'Ship Inertia task form',
            'description' => 'Allow task creation directly from the board.',
            'status' => 'pending',
            'priority' => 'medium',
            'tags' => 'frontend, inertia, board',
            'deadline_at' => '2026-04-15',
        ]);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasNoErrors();

        $task = Task::query()
            ->where('title', 'Ship Inertia task form')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame('pending', $task->status);
        $this->assertSame('medium', $task->priority);
        $this->assertSame(['frontend', 'inertia', 'board'], $task->tags);
        $this->assertSame('2026-04-15', $task->deadline_at?->toDateString());

        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
    }

    public function test_task_board_creation_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->from(route('tasks.board', ['board' => $board]))
            ->actingAs($user)
            ->post(route('tasks.store', ['board' => $board]), [
                'title' => '   ',
                'status' => 'blocked',
                'priority' => 'urgent',
                'tags' => collect(range(1, Task::MAX_TAGS + 1))
                    ->map(fn (int $index): string => "tag-{$index}")
                    ->all(),
            ]);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasErrors(['title', 'status', 'priority', 'tags']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_assignee_can_update_task_status_from_the_board(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status', ['board' => $board, 'task' => $task]),
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
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'status' => 'pending',
            'priority' => 'low',
            'progress' => 10,
            'deadline_at' => '2026-04-12',
        ]);

        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);

        $response = $this->from(route('tasks.board', ['board' => $board]))
            ->actingAs($user)
            ->patch(route('tasks.update', ['board' => $board, 'task' => $task]), [
                'title' => 'Updated task title',
                'description' => 'Updated description',
                'status' => 'in-progress',
                'priority' => 'high',
                'tags' => 'backend, api, release',
                'progress' => 70,
                'deadline_at' => '2026-04-20',
            ]);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
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
            ['backend', 'api', 'release'],
            $task->fresh()->tags,
        );

        $this->assertSame(
            '2026-04-20',
            $task->fresh()->deadline_at?->toDateString(),
        );
    }

    public function test_authenticated_user_can_update_a_board_column_label(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status-labels.update', ['board' => $board, 'status' => 'pending']),
            ['label' => 'Backlog'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('label', 'Backlog')
            ->assertJsonPath('status_labels.pending', 'Backlog');

        $this->assertDatabaseHas('board_columns', [
            'user_id' => $user->id,
            'board_id' => $board->id,
            'status' => 'pending',
            'label' => 'Backlog',
        ]);
    }

    public function test_authenticated_user_can_add_a_board_column(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->post(route('tasks.columns.store', ['board' => $board]), [
            'label' => 'Review',
        ]);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasNoErrors();

        $column = BoardColumn::query()
            ->where('board_id', $board->id)
            ->where('label', 'Review')
            ->first();

        $this->assertNotNull($column);
        $this->assertSame(4, $column->position);
        $this->assertStringStartsWith('column-', $column->status);
    }

    public function test_authenticated_user_can_reorder_board_columns(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.columns.reorder', ['board' => $board, 'status' => 'completed']),
            ['before_status' => 'pending'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('statuses.0', 'completed')
            ->assertJsonPath('statuses.1', 'pending')
            ->assertJsonPath('statuses.2', 'in-progress');

        $this->assertDatabaseHas('board_columns', [
            'board_id' => $board->id,
            'status' => 'completed',
            'position' => 1,
        ]);
        $this->assertDatabaseHas('board_columns', [
            'board_id' => $board->id,
            'status' => 'pending',
            'position' => 2,
        ]);
        $this->assertDatabaseHas('board_columns', [
            'board_id' => $board->id,
            'status' => 'in-progress',
            'position' => 3,
        ]);
    }

    public function test_authenticated_user_can_create_a_new_board(): void
    {
        $user = User::factory()->create();
        $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->post(route('boards.store'), [
            'name' => 'Roadmap',
            'description' => 'Quarterly planning and delivery priorities.',
        ]);

        $board = Board::query()
            ->where('user_id', $user->id)
            ->where('name', 'Roadmap')
            ->first();

        $this->assertNotNull($board);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'user_id' => $user->id,
            'description' => 'Quarterly planning and delivery priorities.',
            'position' => 2,
        ]);
        $this->assertDatabaseHas('board_columns', [
            'board_id' => $board->id,
            'status' => 'pending',
            'position' => 1,
        ]);
    }

    public function test_authenticated_user_can_update_a_board_name(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('boards.update', ['board' => $board]),
            ['name' => 'Product Planning'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('board.id', $board->id)
            ->assertJsonPath('board.name', 'Product Planning')
            ->assertJsonPath('boards.0.name', 'Product Planning');

        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'user_id' => $user->id,
            'name' => 'Product Planning',
        ]);
    }

    public function test_authenticated_user_can_update_a_board_description(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('boards.update', ['board' => $board]),
            ['description' => 'Track roadmap work and cross-team handoffs.'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('board.id', $board->id)
            ->assertJsonPath('board.description', 'Track roadmap work and cross-team handoffs.');

        $this->assertDatabaseHas('boards', [
            'id' => $board->id,
            'user_id' => $user->id,
            'description' => 'Track roadmap work and cross-team handoffs.',
        ]);
    }

    public function test_assignee_can_move_a_task_to_a_custom_board_column(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        $column = BoardColumn::query()->create([
            'user_id' => $user->id,
            'board_id' => $board->id,
            'status' => 'column-review',
            'label' => 'Review',
            'position' => 4,
        ]);

        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status', ['board' => $board, 'task' => $task]),
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
        $board = $this->defaultBoardFor($user);
        $firstTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $secondTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $thirdTask = Task::factory()->create([
            'status' => 'pending',
        ]);

        $this->attachAssignee($user, $board, $firstTask, 1);
        $this->attachAssignee($user, $board, $secondTask, 2);
        $this->attachAssignee($user, $board, $thirdTask, 3);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.reorder', ['board' => $board, 'task' => $thirdTask]),
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
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $firstTask->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $secondTask->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 3,
        ]);
    }

    public function test_assignee_can_move_a_task_to_another_column_and_place_it_before_another_task(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $pendingTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $inProgressTask = Task::factory()->create([
            'status' => 'in-progress',
        ]);
        $anotherInProgressTask = Task::factory()->create([
            'status' => 'in-progress',
        ]);

        $this->attachAssignee($user, $board, $pendingTask, 1);
        $this->attachAssignee($user, $board, $inProgressTask, 1);
        $this->attachAssignee($user, $board, $anotherInProgressTask, 2);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.reorder', ['board' => $board, 'task' => $pendingTask]),
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
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $inProgressTask->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 2,
        ]);
        $this->assertDatabaseHas('task_user', [
            'task_id' => $anotherInProgressTask->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 3,
        ]);
    }

    public function test_authenticated_user_can_add_a_comment_to_a_task(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        $this->attachAssignee($user, $board, $task, 1);

        $response = $this->actingAs($user)->postJson(
            route('tasks.comments.store', ['task' => $task]),
            ['content' => 'I will handle this one after standup.'],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('comment.content', 'I will handle this one after standup.')
            ->assertJsonPath('comment.user.id', $user->id)
            ->assertJsonPath('comment.user.name', $user->name);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'I will handle this one after standup.',
        ]);
    }

    public function test_non_assignee_can_add_a_comment_to_a_task_for_now(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $board = $this->defaultBoardFor($owner);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);

        $this->attachAssignee($owner, $board, $task, 1);

        $response = $this->actingAs($commenter)->postJson(
            route('tasks.comments.store', ['task' => $task]),
            ['content' => 'Reviewed this and left a note.'],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('comment.content', 'Reviewed this and left a note.')
            ->assertJsonPath('comment.user.id', $commenter->id)
            ->assertJsonPath('comment.user.name', $commenter->name);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $commenter->id,
            'content' => 'Reviewed this and left a note.',
        ]);
    }

    public function test_authenticated_user_can_reply_to_a_task_comment(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);
        $parentComment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Initial comment.',
        ]);

        $this->attachAssignee($user, $board, $task, 1);

        $response = $this->actingAs($user)->postJson(
            route('tasks.comments.store', ['task' => $task]),
            [
                'content' => 'Replying with more detail.',
                'parent_id' => $parentComment->id,
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('comment.content', 'Replying with more detail.')
            ->assertJsonPath('comment.parent_id', $parentComment->id)
            ->assertJsonPath('comment.user.id', $user->id);

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'parent_id' => $parentComment->id,
            'content' => 'Replying with more detail.',
        ]);
    }

    public function test_reply_must_target_a_top_level_comment_on_the_same_task(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create([
            'status' => 'pending',
        ]);
        $anotherTask = Task::factory()->create([
            'status' => 'pending',
        ]);
        $parentComment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'content' => 'Top-level comment.',
        ]);
        $replyComment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'parent_id' => $parentComment->id,
            'content' => 'Existing reply.',
        ]);
        $foreignComment = TaskComment::query()->create([
            'task_id' => $anotherTask->id,
            'user_id' => $user->id,
            'content' => 'Comment on another task.',
        ]);

        $this->attachAssignee($user, $board, $task, 1);

        $nestedReplyResponse = $this->actingAs($user)->postJson(
            route('tasks.comments.store', ['task' => $task]),
            [
                'content' => 'Trying to reply to a reply.',
                'parent_id' => $replyComment->id,
            ],
        );

        $nestedReplyResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);

        $crossTaskReplyResponse = $this->actingAs($user)->postJson(
            route('tasks.comments.store', ['task' => $task]),
            [
                'content' => 'Trying to reply across tasks.',
                'parent_id' => $foreignComment->id,
            ],
        );

        $crossTaskReplyResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_task_board_update_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);
        $task = Task::factory()->create();

        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
        ]);

        $response = $this->from(route('tasks.board', ['board' => $board]))
            ->actingAs($user)
            ->patch(route('tasks.update', ['board' => $board, 'task' => $task]), [
                'title' => '   ',
                'description' => str_repeat('a', 1001),
                'status' => 'blocked',
                'priority' => 'urgent',
                'tags' => [str_repeat('x', Task::MAX_TAG_LENGTH + 1)],
                'progress' => 120,
            ]);

        $response
            ->assertRedirect(route('tasks.board', ['board' => $board]))
            ->assertSessionHasErrors([
                'title',
                'description',
                'status',
                'priority',
                'tags.0',
                'progress',
            ]);
    }

    public function test_board_column_labels_require_valid_data(): void
    {
        $user = User::factory()->create();
        $board = $this->defaultBoardFor($user);

        $response = $this->actingAs($user)->patchJson(
            route('tasks.status-labels.update', ['board' => $board, 'status' => 'blocked']),
            ['label' => '   '],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'label']);
    }

    private function defaultBoardFor(User $user): Board
    {
        return Board::ensureDefaultForUser($user);
    }

    private function attachAssignee(
        User $user,
        Board $board,
        Task $task,
        int $sortOrder,
    ): void
    {
        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => $sortOrder,
        ]);
    }
}
