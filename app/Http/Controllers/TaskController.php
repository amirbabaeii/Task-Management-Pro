<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\ReorderTaskAction;
use App\Actions\Tasks\UpdateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Http\Requests\Tasks\ReorderTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Support\BoardTaskAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateTaskAction $createTask,
        private readonly UpdateTaskAction $updateTask,
        private readonly ReorderTaskAction $reorderTask,
        private readonly UpdateTaskStatusAction $updateTaskStatus,
    ) {}

    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);

        $this->createTask->execute($user, $board, $request->validated());

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function update(UpdateTaskRequest $request, Board $board, Task $task): RedirectResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $this->ensureTaskIsOnBoard($user, $board, $task);

        $this->updateTask->execute($board, $task, $request->validated());

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function reorder(ReorderTaskRequest $request, Board $board, Task $task): JsonResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $this->ensureTaskIsOnBoard($user, $board, $task);

        $validated = $request->validated();
        $destinationStatus = $validated['status'];
        $beforeTaskId = $validated['before_id'] ?? null;
        $sourceStatus = $task->status;

        if ($beforeTaskId === $task->id) {
            throw ValidationException::withMessages([
                'before_id' => 'A task cannot be reordered relative to itself.',
            ]);
        }

        if (
            $beforeTaskId !== null
            && ! BoardTaskAssignments::userHasTaskInStatus($user->id, $board->id, $beforeTaskId, $destinationStatus)
        ) {
            throw ValidationException::withMessages([
                'before_id' => 'Choose a task from the destination column.',
            ]);
        }

        $this->reorderTask->execute($user, $board, $task, $destinationStatus, $beforeTaskId);

        return response()->json([
            'task' => $this->taskPayloadForUser($task->fresh(), $user->id, $board->id),
            'orders' => $this->userTaskOrderPayload($user->id, $board->id, [$sourceStatus, $destinationStatus]),
        ]);
    }

    public function updateStatus(Request $request, Board $board, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $this->ensureTaskIsOnBoard($user, $board, $task);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(BoardColumn::statusesForBoard($board))],
        ]);

        $originalStatus = $task->status;
        $destinationStatus = $validated['status'];

        $this->updateTaskStatus->execute($board, $task, $destinationStatus);

        return response()->json([
            'task' => $this->taskPayloadForUser($task->fresh(), $user->id, $board->id),
            'orders' => $this->userTaskOrderPayload($user->id, $board->id, [$originalStatus, $destinationStatus]),
        ]);
    }

    private function resolveBoard(User $user, ?Board $board): Board
    {
        if ($board === null) {
            return $this->ensureUserHasDefaultBoard->execute($user);
        }

        abort_unless((int) $board->user_id === (int) $user->id, 404);

        $this->ensureBoardHasDefaultColumns->execute($board);

        return $board;
    }

    private function ensureTaskIsOnBoard(User $user, Board $board, Task $task): void
    {
        abort_unless(
            BoardTaskAssignments::userHasTaskOnBoard($user->id, $board->id, $task->id),
            404,
        );
    }

    /**
     * @return array{id: int, status: string, sort_order: int}
     */
    private function taskPayloadForUser(Task $task, int $userId, int $boardId): array
    {
        $sortOrder = DB::table('task_user')
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->where('role', 'assignee')
            ->where('task_id', $task->id)
            ->value('sort_order');

        return [
            'id' => $task->id,
            'status' => $task->status,
            'sort_order' => (int) $sortOrder,
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return list<array{id: int, status: string, sort_order: int}>
     */
    private function userTaskOrderPayload(int $userId, int $boardId, array $statuses): array
    {
        $statuses = array_values(array_unique($statuses));

        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->whereIn('tasks.status', $statuses)
            ->orderBy('tasks.status')
            ->orderBy('task_user.sort_order')
            ->orderBy('tasks.id')
            ->get(['tasks.id', 'tasks.status', 'task_user.sort_order'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'status' => $row->status,
                'sort_order' => (int) $row->sort_order,
            ])
            ->all();
    }
}
