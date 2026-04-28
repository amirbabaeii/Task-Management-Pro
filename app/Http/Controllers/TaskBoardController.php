<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\TaskPriority;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Presenters\TaskCommentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
    ) {}

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
            'statusLabels' => BoardColumn::labelsForBoard($board),
            'priorities' => TaskPriority::values(),
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

        $this->ensureBoardHasDefaultColumns->execute($board);

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
            if (! $request->has('name') && ! $request->has('description')) {
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

    private function resolveBoard(User $user, ?Board $board): Board
    {
        if ($board === null) {
            return $this->ensureUserHasDefaultBoard->execute($user);
        }

        abort_unless((int) $board->user_id === (int) $user->id, 404);

        $this->ensureBoardHasDefaultColumns->execute($board);

        return $board;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
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

    /**
     * @return list<array<string, mixed>>
     */
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
            ->map(fn (Task $task): array => [
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
                    ->map(fn (TaskComment $comment): array => TaskCommentPresenter::toArray($comment))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
