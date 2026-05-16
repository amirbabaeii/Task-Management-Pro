<?php

namespace Tests\Feature;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskChecklistItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_can_add_checklist_item_to_task(): void
    {
        [$owner, $collaborator, $board, $task] = $this->sharedBoardTask();

        $response = $this->actingAs($collaborator)->postJson(
            route('tasks.checklist-items.store', ['board' => $board, 'task' => $task]),
            ['title' => 'Confirm rollout owner'],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('checklist_item.title', 'Confirm rollout owner')
            ->assertJsonPath('checklist_item.completed', false)
            ->assertJsonPath('checklist_item.position', 1);

        $this->assertDatabaseHas('task_checklist_items', [
            'task_id' => $task->id,
            'title' => 'Confirm rollout owner',
            'position' => 1,
        ]);

        $activity = TaskActivity::query()
            ->where('task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(TaskActivityKind::ChecklistItemAdded, $activity->kind);
        $this->assertSame(['title' => 'Confirm rollout owner'], $activity->payload);
    }

    public function test_board_member_can_update_checklist_item(): void
    {
        [$owner, $collaborator, $board, $task] = $this->sharedBoardTask();
        $item = $task->checklistItems()->create([
            'title' => 'Draft notes',
            'position' => 1,
        ]);

        $response = $this->actingAs($collaborator)->patchJson(
            route('tasks.checklist-items.update', [
                'board' => $board,
                'task' => $task,
                'checklistItem' => $item,
            ]),
            [
                'title' => 'Draft launch notes',
                'completed' => true,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('checklist_item.title', 'Draft launch notes')
            ->assertJsonPath('checklist_item.completed', true);

        $item->refresh();

        $this->assertSame('Draft launch notes', $item->title);
        $this->assertNotNull($item->completed_at);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'kind' => TaskActivityKind::ChecklistItemRenamed->value,
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'kind' => TaskActivityKind::ChecklistItemCompleted->value,
        ]);

        $this->actingAs($collaborator)
            ->patchJson(route('tasks.checklist-items.update', [
                'board' => $board,
                'task' => $task,
                'checklistItem' => $item,
            ]), ['completed' => false])
            ->assertOk()
            ->assertJsonPath('checklist_item.completed', false);

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'kind' => TaskActivityKind::ChecklistItemReopened->value,
        ]);
    }

    public function test_board_member_can_delete_checklist_item(): void
    {
        [$owner, $collaborator, $board, $task] = $this->sharedBoardTask();
        $item = $task->checklistItems()->create([
            'title' => 'Remove me',
            'position' => 1,
        ]);

        $this->actingAs($collaborator)
            ->deleteJson(route('tasks.checklist-items.destroy', [
                'board' => $board,
                'task' => $task,
                'checklistItem' => $item,
            ]))
            ->assertOk()
            ->assertJsonPath('id', $item->id);

        $this->assertDatabaseMissing('task_checklist_items', [
            'id' => $item->id,
        ]);

        $activity = TaskActivity::query()
            ->where('task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(TaskActivityKind::ChecklistItemDeleted, $activity->kind);
        $this->assertSame(['title' => 'Remove me'], $activity->payload);
    }

    public function test_non_member_cannot_add_checklist_item(): void
    {
        [$owner, $collaborator, $board, $task] = $this->sharedBoardTask();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson(
                route('tasks.checklist-items.store', ['board' => $board, 'task' => $task]),
                ['title' => 'Sneak onto task'],
            )
            ->assertNotFound();
    }

    public function test_cannot_update_checklist_item_from_another_task(): void
    {
        [$owner, $collaborator, $board, $task] = $this->sharedBoardTask();
        $otherTask = Task::factory()->create(['status' => 'pending']);
        $otherTask->users()->attach($owner->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 2,
        ]);
        $item = $otherTask->checklistItems()->create([
            'title' => 'Wrong task',
            'position' => 1,
        ]);

        $this->actingAs($collaborator)
            ->patchJson(route('tasks.checklist-items.update', [
                'board' => $board,
                'task' => $task,
                'checklistItem' => $item,
            ]), ['completed' => true])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User, 2: Board, 3: Task}
     */
    private function sharedBoardTask(): array
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $board = app(EnsureUserHasDefaultBoardAction::class)->execute($owner);
        $board->members()->attach($collaborator->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $task = Task::factory()->create([
            'status' => 'pending',
        ]);
        $task->users()->attach($owner->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        return [$owner, $collaborator, $board, $task];
    }
}
