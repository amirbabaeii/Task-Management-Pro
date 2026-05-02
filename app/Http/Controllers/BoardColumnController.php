<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\CreateBoardColumnAction;
use App\Actions\BoardColumns\DeleteBoardColumnAction;
use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\BoardColumns\ReorderBoardColumnsAction;
use App\Actions\BoardColumns\UpdateBoardColumnLabelAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Http\Requests\BoardColumns\DeleteBoardColumnRequest;
use App\Http\Requests\BoardColumns\ReorderBoardColumnRequest;
use App\Http\Requests\BoardColumns\StoreBoardColumnRequest;
use App\Http\Requests\BoardColumns\UpdateBoardColumnLabelRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class BoardColumnController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateBoardColumnAction $createColumn,
        private readonly ReorderBoardColumnsAction $reorderColumns,
        private readonly UpdateBoardColumnLabelAction $updateColumnLabel,
        private readonly DeleteBoardColumnAction $deleteColumn,
    ) {}

    public function store(StoreBoardColumnRequest $request, Board $board): RedirectResponse
    {
        $board = $this->resolveBoard($request->user(), $board);

        $this->createColumn->execute($board, $request->validated('label'));

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function reorder(ReorderBoardColumnRequest $request, Board $board, string $status): JsonResponse
    {
        $board = $this->resolveBoard($request->user(), $board);

        $this->reorderColumns->execute(
            $board,
            $request->validated('status'),
            $request->validated('before_status'),
        );

        $fresh = $board->fresh();

        return response()->json([
            'statuses' => BoardColumn::statusesForBoard($fresh),
            'status_labels' => BoardColumn::labelsForBoard($fresh),
        ]);
    }

    public function updateLabel(UpdateBoardColumnLabelRequest $request, Board $board, string $status): JsonResponse
    {
        $board = $this->resolveBoard($request->user(), $board);

        $this->updateColumnLabel->execute(
            $board,
            $request->validated('status'),
            $request->validated('label'),
        );

        return response()->json([
            'status' => $request->validated('status'),
            'label' => $request->validated('label'),
            'status_labels' => BoardColumn::labelsForBoard($board->fresh()),
        ]);
    }

    public function destroy(DeleteBoardColumnRequest $request, Board $board, string $status): JsonResponse
    {
        $board = $this->resolveBoard($request->user(), $board);

        $this->deleteColumn->execute(
            $board,
            $status,
            $request->validated('move_tasks_to'),
        );

        $fresh = $board->fresh();

        return response()->json([
            'statuses' => BoardColumn::statusesForBoard($fresh),
            'status_labels' => BoardColumn::labelsForBoard($fresh),
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
}
