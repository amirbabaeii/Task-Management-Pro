<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\ReorderTaskRequest;
use App\Http\Requests\Tasks\StoreTaskCommentRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request, ?Board $board = null): Response
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);

        return Inertia::render('Tasks/Board', [
            'boards' => $this->boardListForUser($user),
            'currentBoard' => [
                'id' => $board->id,
                'name' => $board->name,
                'description' => $board->description,
            ],
            'tasks' => $this->boardTasksForUser($user, $board),
            'statuses' => BoardColumn::statusesForBoard($board),
            'statusLabels' => $this->statusLabelsForBoard($board),
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function storeBoard(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = validator(
            [
                'name' => trim((string) $request->input('name')),
                'description' => $request->filled('description')
                    ? trim((string) $request->input('description'))
                    : null,
            ],
            [
                'name' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:280'],
            ],
        )->validate();

        $board = $user->boards()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'position' => Board::nextPositionForUser($user),
        ]);

        BoardColumn::ensureDefaultsForBoard($board);

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function updateBoard(Request $request, Board $board): JsonResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $payload = [];

        if ($request->has('name')) {
            $payload['name'] = trim((string) $request->input('name'));
        }

        if ($request->has('description')) {
            $payload['description'] = trim((string) $request->input('description')) ?: null;
        }

        $validated = validator(
            $payload,
            [
                'name' => ['sometimes', 'required', 'string', 'max:100'],
                'description' => ['sometimes', 'nullable', 'string', 'max:280'],
            ],
        )->after(function ($validator) use ($request): void {
            if (!$request->has('name') && !$request->has('description')) {
                $validator->errors()->add(
                    'board',
                    'Provide a board name or description to update.',
                );
            }
        })->validate();

        $updates = [];

        if ($request->has('name')) {
            $updates['name'] = $validated['name'];
        }

        if ($request->has('description')) {
            $updates['description'] = $validated['description'] ?? null;
        }

        $board->update($updates);

        return response()->json([
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'description' => $board->description,
            ],
            'boards' => $this->boardListForUser($user),
        ]);
    }

    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $validated = $request->validated();

        DB::transaction(function () use ($user, $board, $validated): void {
            $task = Task::create($validated);

            $task->users()->attach($user->id, [
                'board_id' => $board->id,
                'role' => 'assignee',
                'sort_order' => $this->nextSortOrderForUserStatus(
                    $user->id,
                    $board->id,
                    $task->status,
                ),
            ]);
        });

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function storeComment(
        StoreTaskCommentRequest $request,
        Task $task,
    ): JsonResponse {
        $comment = $task->comments()->create([
            'parent_id' => $request->validated('parent_id'),
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $comment->load('user:id,name', 'replies.user:id,name');

        return response()->json([
            'comment' => $this->commentPayload($comment),
        ], 201);
    }

    public function storeColumn(Request $request, Board $board): RedirectResponse
    {
        $board = $this->resolveBoard($request->user(), $board);
        $validated = validator(
            [
                'label' => trim((string) $request->input('label')),
            ],
            [
                'label' => ['required', 'string', 'max:40'],
            ],
        )->validate();

        BoardColumn::query()->create([
            'user_id' => $board->user_id,
            'board_id' => $board->id,
            'status' => 'column-'.Str::lower((string) Str::ulid()),
            'label' => $validated['label'],
            'position' => BoardColumn::nextPositionForBoard($board),
        ]);

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function reorderColumn(
        Request $request,
        Board $board,
        string $status,
    ): JsonResponse {
        $board = $this->resolveBoard($request->user(), $board);
        $availableStatuses = BoardColumn::statusesForBoard($board);

        $validated = validator(
            [
                'status' => $status,
                'before_status' => $request->input('before_status'),
            ],
            [
                'status' => ['required', 'string', Rule::in($availableStatuses)],
                'before_status' => ['nullable', 'string', Rule::in($availableStatuses)],
            ],
        )->after(function ($validator) use ($status, $request): void {
            if ($status === $request->input('before_status')) {
                $validator->errors()->add(
                    'before_status',
                    'A column cannot be reordered relative to itself.',
                );
            }
        })->validate();

        $reorderedStatuses = array_values(array_filter(
            $availableStatuses,
            fn (string $availableStatus): bool => $availableStatus !== $validated['status'],
        ));

        $insertAt = $validated['before_status'] === null
            ? count($reorderedStatuses)
            : array_search($validated['before_status'], $reorderedStatuses, true);

        if ($insertAt === false) {
            $insertAt = count($reorderedStatuses);
        }

        array_splice($reorderedStatuses, $insertAt, 0, [$validated['status']]);

        BoardColumn::syncOrderForBoard($board, $reorderedStatuses);

        return response()->json([
            'statuses' => BoardColumn::statusesForBoard($board->fresh()),
            'status_labels' => $this->statusLabelsForBoard($board->fresh()),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        Board $board,
        Task $task,
    ): RedirectResponse {
        $board = $this->resolveBoard($request->user(), $board);
        $this->ensureTaskIsOnBoardForUser($request->user(), $board, $task);

        $validated = $request->validated();
        $originalStatus = $task->status;

        DB::transaction(function () use (
            $board,
            $task,
            $validated,
            $originalStatus,
        ): void {
            $task->fill($validated);
            $task->save();

            if ($originalStatus !== $task->status) {
                $this->moveTaskToStatusForAssignees(
                    $task,
                    $originalStatus,
                    $task->status,
                    $board->id,
                );
            }
        });

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function reorder(
        ReorderTaskRequest $request,
        Board $board,
        Task $task,
    ): JsonResponse {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);
        $this->ensureTaskIsOnBoardForUser($user, $board, $task);

        $validated = $request->validated();
        $destinationStatus = $validated['status'];
        $beforeTaskId = $validated['before_id'] ?? null;
        $sourceStatus = $task->status;

        if ($beforeTaskId === $task->id) {
            throw ValidationException::withMessages([
                'before_id' => 'A task cannot be reordered relative to itself.',
            ]);
        }

        if ($beforeTaskId !== null && !$this->userHasTaskInStatus(
            $user->id,
            $board->id,
            $beforeTaskId,
            $destinationStatus,
        )) {
            throw ValidationException::withMessages([
                'before_id' => 'Choose a task from the destination column.',
            ]);
        }

        DB::transaction(function () use (
            $user,
            $board,
            $task,
            $sourceStatus,
            $destinationStatus,
            $beforeTaskId,
        ): void {
            if ($sourceStatus !== $destinationStatus) {
                $task->status = $destinationStatus;
                $task->save();

                $this->moveTaskToStatusForAssignees(
                    $task,
                    $sourceStatus,
                    $destinationStatus,
                    $board->id,
                );
            }

            $this->reorderTaskForUser(
                $user->id,
                $board->id,
                $task,
                $destinationStatus,
                $beforeTaskId,
            );
        });

        return response()->json([
            'task' => $this->taskPayloadForUser($task->fresh(), $user->id, $board->id),
            'orders' => $this->userTaskOrderPayload($user->id, $board->id, [
                $sourceStatus,
                $destinationStatus,
            ]),
        ]);
    }

    public function updateStatus(
        Request $request,
        Board $board,
        Task $task,
    ): JsonResponse {
        $this->authorize('update', $task);

        $board = $this->resolveBoard($request->user(), $board);
        $this->ensureTaskIsOnBoardForUser($request->user(), $board, $task);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(BoardColumn::statusesForBoard($board))],
        ]);

        $originalStatus = $task->status;
        $destinationStatus = $validated['status'];

        DB::transaction(function () use (
            $board,
            $task,
            $originalStatus,
            $destinationStatus,
        ): void {
            $task->status = $destinationStatus;
            $task->save();

            if ($originalStatus !== $destinationStatus) {
                $this->moveTaskToStatusForAssignees(
                    $task,
                    $originalStatus,
                    $destinationStatus,
                    $board->id,
                );
            }
        });

        return response()->json([
            'task' => $this->taskPayloadForUser(
                $task->fresh(),
                $request->user()->id,
                $board->id,
            ),
            'orders' => $this->userTaskOrderPayload(
                $request->user()->id,
                $board->id,
                [$originalStatus, $destinationStatus],
            ),
        ]);
    }

    public function updateStatusLabel(
        Request $request,
        Board $board,
        string $status,
    ): JsonResponse {
        $board = $this->resolveBoard($request->user(), $board);
        $validated = validator(
            [
                'status' => $status,
                'label' => trim((string) $request->input('label')),
            ],
            [
                'status' => ['required', 'string', Rule::in(BoardColumn::statusesForBoard($board))],
                'label' => ['required', 'string', 'max:40'],
            ],
        )->validate();

        $board->columns()
            ->where('status', $validated['status'])
            ->update([
                'label' => $validated['label'],
            ]);

        return response()->json([
            'status' => $validated['status'],
            'label' => $validated['label'],
            'status_labels' => $this->statusLabelsForBoard($board->fresh()),
        ]);
    }

    private function resolveBoard(User $user, ?Board $board): Board
    {
        $board = $board ?? Board::ensureDefaultForUser($user);

        abort_unless((int) $board->user_id === (int) $user->id, 404);

        BoardColumn::ensureDefaultsForBoard($board);

        return $board;
    }

    private function boardListForUser(User $user): array
    {
        return Board::orderedForUser($user)
            ->map(fn (Board $board): array => [
                'id' => $board->id,
                'name' => $board->name,
            ])
            ->values()
            ->all();
    }

    private function boardTasksForUser(User $user, Board $board): array
    {
        return $user->assignedTasks()
            ->wherePivot('board_id', $board->id)
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.description',
                'tasks.status',
                'tasks.priority',
                'tasks.tags',
                'tasks.deadline_at',
                'tasks.progress',
                'tasks.created_at',
            ])
            ->with([
                'comments' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with([
                        'user:id,name',
                        'replies.user:id,name',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->orderBy('task_user.sort_order')
            ->orderBy('tasks.id')
            ->get()
            ->map(function (Task $task): array {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'tags' => $task->tags ?? [],
                    'deadline_at' => $task->deadline_at,
                    'progress' => $task->progress,
                    'created_at' => $task->created_at,
                    'sort_order' => (int) $task->pivot->sort_order,
                    'comments' => $task->comments
                        ->map(fn (TaskComment $comment): array => $this->commentPayload($comment))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function statusLabelsForBoard(Board $board): array
    {
        return BoardColumn::labelsForBoard($board);
    }

    private function ensureTaskIsOnBoardForUser(
        User $user,
        Board $board,
        Task $task,
    ): void {
        abort_unless(
            $this->userHasTaskOnBoard($user->id, $board->id, $task->id),
            404,
        );
    }

    private function moveTaskToStatusForAssignees(
        Task $task,
        string $sourceStatus,
        string $destinationStatus,
        int $sourceBoardId,
    ): void {
        $assignments = DB::table('task_user')
            ->where('task_id', $task->id)
            ->where('role', 'assignee')
            ->get(['user_id', 'board_id']);

        foreach ($assignments as $assignment) {
            if ($assignment->board_id === null) {
                continue;
            }

            $userId = (int) $assignment->user_id;
            $boardId = (int) $assignment->board_id;

            $this->normalizeUserStatusOrder(
                $userId,
                $boardId,
                $sourceStatus,
                $task->id,
            );

            $this->ensureBoardColumnForBoardStatus(
                $boardId,
                $destinationStatus,
                $sourceBoardId,
            );

            $destinationTaskIds = $this->assignedTaskIdsForStatus(
                $userId,
                $boardId,
                $destinationStatus,
                $task->id,
            );

            $destinationTaskIds[] = $task->id;

            $this->syncUserTaskOrder($userId, $boardId, $destinationTaskIds);
        }
    }

    private function reorderTaskForUser(
        int $userId,
        int $boardId,
        Task $task,
        string $status,
        ?int $beforeTaskId,
    ): void {
        $destinationTaskIds = $this->assignedTaskIdsForStatus(
            $userId,
            $boardId,
            $status,
            $task->id,
        );

        $insertAt = $beforeTaskId === null
            ? count($destinationTaskIds)
            : array_search($beforeTaskId, $destinationTaskIds, true);

        if ($insertAt === false) {
            $insertAt = count($destinationTaskIds);
        }

        array_splice($destinationTaskIds, $insertAt, 0, [$task->id]);

        $this->syncUserTaskOrder($userId, $boardId, $destinationTaskIds);
    }

    private function normalizeUserStatusOrder(
        int $userId,
        int $boardId,
        string $status,
        ?int $excludingTaskId = null,
    ): void {
        $this->syncUserTaskOrder(
            $userId,
            $boardId,
            $this->assignedTaskIdsForStatus(
                $userId,
                $boardId,
                $status,
                $excludingTaskId,
            ),
        );
    }

    private function assignedTaskIdsForStatus(
        int $userId,
        int $boardId,
        string $status,
        ?int $excludingTaskId = null,
    ): array {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->when($excludingTaskId !== null, function ($query) use ($excludingTaskId) {
                $query->where('tasks.id', '!=', $excludingTaskId);
            })
            ->orderBy('task_user.sort_order')
            ->orderBy('tasks.id')
            ->pluck('tasks.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function nextSortOrderForUserStatus(
        int $userId,
        int $boardId,
        string $status,
    ): int {
        $sortOrder = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->max('task_user.sort_order');

        return ((int) $sortOrder) + 1;
    }

    private function syncUserTaskOrder(
        int $userId,
        int $boardId,
        array $taskIds,
    ): void {
        $timestamp = now();

        foreach (array_values($taskIds) as $index => $taskId) {
            DB::table('task_user')
                ->where('user_id', $userId)
                ->where('board_id', $boardId)
                ->where('role', 'assignee')
                ->where('task_id', $taskId)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    private function taskPayloadForUser(
        Task $task,
        int $userId,
        int $boardId,
    ): array {
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

    private function userTaskOrderPayload(
        int $userId,
        int $boardId,
        array $statuses,
    ): array {
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
            ->get([
                'tasks.id',
                'tasks.status',
                'task_user.sort_order',
            ])
            ->map(function (object $task): array {
                return [
                    'id' => (int) $task->id,
                    'status' => $task->status,
                    'sort_order' => (int) $task->sort_order,
                ];
            })
            ->all();
    }

    private function commentPayload(TaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name ?? 'Unknown user',
            ],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies
                    ->map(fn (TaskComment $reply): array => $this->commentPayload($reply))
                    ->values()
                    ->all()
                : [],
        ];
    }

    private function userHasTaskOnBoard(
        int $userId,
        int $boardId,
        int $taskId,
    ): bool {
        return DB::table('task_user')
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->where('role', 'assignee')
            ->where('task_id', $taskId)
            ->exists();
    }

    private function userHasTaskInStatus(
        int $userId,
        int $boardId,
        int $taskId,
        string $status,
    ): bool {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.id', $taskId)
            ->where('tasks.status', $status)
            ->exists();
    }

    private function ensureBoardColumnForBoardStatus(
        int $boardId,
        string $status,
        int $sourceBoardId,
    ): void {
        $exists = BoardColumn::query()
            ->where('board_id', $boardId)
            ->where('status', $status)
            ->exists();

        if ($exists) {
            return;
        }

        $sourceBoard = Board::query()->findOrFail($sourceBoardId);
        $label = $sourceBoard->columns()
            ->where('status', $status)
            ->value('label')
            ?? BoardColumn::query()
                ->where('status', $status)
                ->value('label')
            ?? (string) Str::of($status)->replace('-', ' ')->title();

        $targetBoard = Board::query()->findOrFail($boardId);

        BoardColumn::query()->create([
            'user_id' => $targetBoard->user_id,
            'board_id' => $targetBoard->id,
            'status' => $status,
            'label' => trim((string) $label),
            'position' => BoardColumn::nextPositionForBoard($targetBoard),
        ]);
    }
}
