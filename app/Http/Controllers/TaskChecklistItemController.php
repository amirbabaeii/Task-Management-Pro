<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\TaskChecklistItems\CreateTaskChecklistItemAction;
use App\Actions\TaskChecklistItems\DeleteTaskChecklistItemAction;
use App\Actions\TaskChecklistItems\UpdateTaskChecklistItemAction;
use App\Http\Requests\Tasks\StoreTaskChecklistItemRequest;
use App\Http\Requests\Tasks\UpdateTaskChecklistItemRequest;
use App\Models\Board;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Support\BoardTaskAssignments;
use App\Support\Presenters\TaskChecklistItemPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskChecklistItemController extends Controller
{
    public function __construct(
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateTaskChecklistItemAction $createItem,
        private readonly UpdateTaskChecklistItemAction $updateItem,
        private readonly DeleteTaskChecklistItemAction $deleteItem,
    ) {}

    public function store(
        StoreTaskChecklistItemRequest $request,
        Board $board,
        Task $task,
    ): JsonResponse {
        $this->resolveBoardTask($board, $task);

        $item = $this->createItem->execute($task, $request->validated());

        return response()->json([
            'checklist_item' => TaskChecklistItemPresenter::toArray($item),
        ], 201);
    }

    public function update(
        UpdateTaskChecklistItemRequest $request,
        Board $board,
        Task $task,
        TaskChecklistItem $checklistItem,
    ): JsonResponse {
        $this->resolveBoardTask($board, $task);
        $this->ensureChecklistItemBelongsToTask($checklistItem, $task);

        $item = $this->updateItem->execute($checklistItem, $request->validated());

        return response()->json([
            'checklist_item' => TaskChecklistItemPresenter::toArray($item),
        ]);
    }

    public function destroy(
        Request $request,
        Board $board,
        Task $task,
        TaskChecklistItem $checklistItem,
    ): JsonResponse {
        $this->resolveBoardTask($board, $task);
        $this->ensureChecklistItemBelongsToTask($checklistItem, $task);

        $this->deleteItem->execute($checklistItem);

        return response()->json(['id' => $checklistItem->id]);
    }

    private function resolveBoardTask(Board $board, Task $task): void
    {
        $this->authorize('update', $board);
        $this->ensureBoardHasDefaultColumns->execute($board);

        abort_unless(
            BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id),
            404,
        );
    }

    private function ensureChecklistItemBelongsToTask(
        TaskChecklistItem $item,
        Task $task,
    ): void {
        abort_unless((int) $item->task_id === (int) $task->id, 404);
    }
}
