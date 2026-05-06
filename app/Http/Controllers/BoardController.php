<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\Boards\CreateBoardAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Actions\Boards\UpdateBoardAction;
use App\Enums\TaskPriority;
use App\Http\Requests\Boards\StoreBoardRequest;
use App\Http\Requests\Boards\UpdateBoardRequest;
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

class BoardController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateBoardAction $createBoard,
        private readonly UpdateBoardAction $updateBoard,
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
            'members' => $this->boardMembersForBoard($board),
        ]);
    }

    public function store(StoreBoardRequest $request): RedirectResponse
    {
        $board = $this->createBoard->execute($request->user(), $request->validated());

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function update(UpdateBoardRequest $request, Board $board): JsonResponse
    {
        $user = $request->user();
        $board = $this->resolveBoard($user, $board);

        $this->updateBoard->execute($board, $request->validated());

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

        $this->authorize('view', $board);

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
                'assignees' => fn ($query) => $query
                    ->wherePivot('board_id', $board->id)
                    ->select(['users.id', 'users.name', 'users.email']),
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
                'assignees' => $task->assignees
                    ->map(fn (User $assignee): array => [
                        'id' => $assignee->id,
                        'name' => $assignee->name,
                        'email' => $assignee->email,
                    ])
                    ->values()
                    ->all(),
                'comments' => $task->comments
                    ->map(fn (TaskComment $comment): array => TaskCommentPresenter::toArray($comment))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    private function boardMembersForBoard(Board $board): array
    {
        return $board->members()
            ->orderByRaw("CASE board_members.role WHEN 'owner' THEN 0 ELSE 1 END")
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])
            ->values()
            ->all();
    }
}
