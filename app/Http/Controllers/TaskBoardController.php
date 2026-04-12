<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Http\Requests\Tasks\ReorderTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Task;
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
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Tasks/Board', [
            'tasks' => $this->boardTasksForUser($user),
            'statuses' => BoardColumn::statusesForUser($user),
            'statusLabels' => $this->statusLabelsForUser($user),
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $task = Task::create($validated);

            $task->users()->attach($user->id, [
                'role' => 'assignee',
                'sort_order' => $this->nextSortOrderForUserStatus(
                    $user->id,
                    $task->status,
                ),
            ]);
        });

        return redirect()->route('tasks.board');
    }

    public function storeColumn(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = validator(
            [
                'label' => trim((string) $request->input('label')),
            ],
            [
                'label' => ['required', 'string', 'max:40'],
            ],
        )->validate();

        BoardColumn::query()->create([
            'user_id' => $user->id,
            'status' => 'column-'.Str::lower((string) Str::ulid()),
            'label' => $validated['label'],
            'position' => BoardColumn::nextPositionForUser($user),
        ]);

        return redirect()->route('tasks.board');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();
        $originalStatus = $task->status;

        DB::transaction(function () use ($request, $task, $validated, $originalStatus): void {
            $task->fill($validated);
            $task->save();

            if ($originalStatus !== $task->status) {
                $this->moveTaskToStatusForAssignees(
                    $task,
                    $originalStatus,
                    $task->status,
                    $request->user()->id,
                );
            }
        });

        return redirect()->route('tasks.board');
    }

    public function reorder(ReorderTaskRequest $request, Task $task): JsonResponse
    {
        $user = $request->user();
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
            $beforeTaskId,
            $destinationStatus,
        )) {
            throw ValidationException::withMessages([
                'before_id' => 'Choose a task from the destination column.',
            ]);
        }

        DB::transaction(function () use (
            $user,
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
                    $user->id,
                );
            }

            $this->reorderTaskForUser(
                $user->id,
                $task,
                $destinationStatus,
                $beforeTaskId,
            );
        });

        return response()->json([
            'task' => $this->taskPayloadForUser($task->fresh(), $user->id),
            'orders' => $this->userTaskOrderPayload($user->id, [
                $sourceStatus,
                $destinationStatus,
            ]),
        ]);
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(BoardColumn::statusesForUser($request->user()))],
        ]);

        $originalStatus = $task->status;
        $destinationStatus = $validated['status'];

        DB::transaction(function () use ($request, $task, $originalStatus, $destinationStatus): void {
            $task->status = $destinationStatus;
            $task->save();

            if ($originalStatus !== $destinationStatus) {
                $this->moveTaskToStatusForAssignees(
                    $task,
                    $originalStatus,
                    $destinationStatus,
                    $request->user()->id,
                );
            }
        });

        return response()->json([
            'task' => $this->taskPayloadForUser($task->fresh(), $request->user()->id),
            'orders' => $this->userTaskOrderPayload($request->user()->id, [
                $originalStatus,
                $destinationStatus,
            ]),
        ]);
    }

    public function updateStatusLabel(Request $request, string $status): JsonResponse
    {
        $validated = validator(
            [
                'status' => $status,
                'label' => trim((string) $request->input('label')),
            ],
            [
                'status' => ['required', 'string', Rule::in(BoardColumn::statusesForUser($request->user()))],
                'label' => ['required', 'string', 'max:40'],
            ],
        )->validate();

        $user = $request->user();
        $user->boardColumns()
            ->where('status', $validated['status'])
            ->update([
                'label' => $validated['label'],
            ]);

        return response()->json([
            'status' => $validated['status'],
            'label' => $validated['label'],
            'status_labels' => $this->statusLabelsForUser($user->fresh()),
        ]);
    }

    private function boardTasksForUser(User $user): array
    {
        return $user->assignedTasks()
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.description',
                'tasks.status',
                'tasks.priority',
                'tasks.deadline_at',
                'tasks.progress',
                'tasks.created_at',
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
                    'deadline_at' => $task->deadline_at,
                    'progress' => $task->progress,
                    'created_at' => $task->created_at,
                    'sort_order' => (int) $task->pivot->sort_order,
                ];
            })
            ->values()
            ->all();
    }

    private function statusLabelsForUser(User $user): array
    {
        return BoardColumn::labelsForUser($user);
    }

    private function moveTaskToStatusForAssignees(
        Task $task,
        string $sourceStatus,
        string $destinationStatus,
        int $sourceUserId,
    ): void {
        $userIds = DB::table('task_user')
            ->where('task_id', $task->id)
            ->where('role', 'assignee')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->normalizeUserStatusOrder(
                (int) $userId,
                $sourceStatus,
                $task->id,
            );

            $this->ensureBoardColumnForUserStatus(
                (int) $userId,
                $destinationStatus,
                $sourceUserId,
            );

            $destinationTaskIds = $this->assignedTaskIdsForStatus(
                (int) $userId,
                $destinationStatus,
                $task->id,
            );

            $destinationTaskIds[] = $task->id;

            $this->syncUserTaskOrder((int) $userId, $destinationTaskIds);
        }
    }

    private function reorderTaskForUser(
        int $userId,
        Task $task,
        string $status,
        ?int $beforeTaskId,
    ): void {
        $destinationTaskIds = $this->assignedTaskIdsForStatus(
            $userId,
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

        $this->syncUserTaskOrder($userId, $destinationTaskIds);
    }

    private function normalizeUserStatusOrder(
        int $userId,
        string $status,
        ?int $excludingTaskId = null,
    ): void {
        $this->syncUserTaskOrder(
            $userId,
            $this->assignedTaskIdsForStatus($userId, $status, $excludingTaskId),
        );
    }

    private function assignedTaskIdsForStatus(
        int $userId,
        string $status,
        ?int $excludingTaskId = null,
    ): array {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
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
        string $status,
    ): int {
        $sortOrder = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->max('task_user.sort_order');

        return ((int) $sortOrder) + 1;
    }

    private function syncUserTaskOrder(int $userId, array $taskIds): void
    {
        $timestamp = now();

        foreach (array_values($taskIds) as $index => $taskId) {
            DB::table('task_user')
                ->where('user_id', $userId)
                ->where('role', 'assignee')
                ->where('task_id', $taskId)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    private function taskPayloadForUser(Task $task, int $userId): array
    {
        $sortOrder = DB::table('task_user')
            ->where('user_id', $userId)
            ->where('role', 'assignee')
            ->where('task_id', $task->id)
            ->value('sort_order');

        return [
            'id' => $task->id,
            'status' => $task->status,
            'sort_order' => (int) $sortOrder,
        ];
    }

    private function userTaskOrderPayload(int $userId, array $statuses): array
    {
        $statuses = array_values(array_unique($statuses));

        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
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

    private function userHasTaskInStatus(
        int $userId,
        int $taskId,
        string $status,
    ): bool {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.id', $taskId)
            ->where('tasks.status', $status)
            ->exists();
    }

    private function ensureBoardColumnForUserStatus(
        int $userId,
        string $status,
        int $sourceUserId,
    ): void {
        $exists = BoardColumn::query()
            ->where('user_id', $userId)
            ->where('status', $status)
            ->exists();

        if ($exists) {
            return;
        }

        $sourceUser = User::query()->findOrFail($sourceUserId);
        $label = $sourceUser->boardColumns()
            ->where('status', $status)
            ->value('label')
            ?? BoardColumn::query()
                ->where('status', $status)
                ->value('label')
            ?? (string) Str::of($status)->replace('-', ' ')->title();

        $targetUser = User::query()->findOrFail($userId);

        BoardColumn::query()->create([
            'user_id' => $userId,
            'status' => $status,
            'label' => trim((string) $label),
            'position' => BoardColumn::nextPositionForUser($targetUser),
        ]);
    }
}
