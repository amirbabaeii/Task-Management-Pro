<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskAction;
use App\Enums\TaskPriority;
use App\Http\ApiResponse;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function __construct(
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateTaskAction $createTask,
        private readonly UpdateTaskAction $updateTask,
    ) {}

    public function index(Request $request, Board $board): JsonResponse
    {
        $this->authorize('view', $board);
        $this->ensureBoardHasDefaultColumns->execute($board);

        $priority = $request->input('priority');

        $tasks = Task::query()
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('task_user')
                ->whereColumn('task_user.task_id', 'tasks.id')
                ->where('task_user.board_id', $board->id)
                ->where('task_user.role', 'assignee'))
            ->whereNull('tasks.archived_at')
            ->when(
                in_array($priority, TaskPriority::values(), true),
                fn ($query) => $query->where('priority', $priority),
            )
            ->with([
                'assignees' => fn ($query) => $query
                    ->wherePivot('board_id', $board->id)
                    ->select(['users.id', 'users.name', 'users.email']),
            ])
            ->orderBy('tasks.id')
            ->paginate(10)
            ->through(fn (Task $task) => (new TaskResource($task))->resolve());

        return ApiResponse::success($tasks, 'List of tasks successfully received');
    }

    public function store(StoreTaskRequest $request, Board $board): JsonResponse
    {
        $this->ensureBoardHasDefaultColumns->execute($board);
        $task = $this->createTask->execute(
            $request->user(),
            $board,
            $request->validated(),
        );
        $this->loadBoardAssignees($task, $board);

        return ApiResponse::success(
            new TaskResource($task),
            'Task successfully created',
            201,
        );
    }

    public function update(
        UpdateTaskRequest $request,
        Board $board,
        Task $task,
    ): JsonResponse {
        $this->ensureBoardHasDefaultColumns->execute($board);
        abort_unless(
            BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id),
            404,
        );

        $task = $this->updateTask->execute(
            $board,
            $task,
            $request->validated(),
        );
        $this->loadBoardAssignees($task, $board);

        return ApiResponse::success(
            new TaskResource($task),
            'Task successfully updated',
        );
    }

    private function loadBoardAssignees(Task $task, Board $board): void
    {
        $task->load([
            'assignees' => fn ($query) => $query
                ->wherePivot('board_id', $board->id)
                ->select(['users.id', 'users.name', 'users.email']),
        ]);
    }
}
