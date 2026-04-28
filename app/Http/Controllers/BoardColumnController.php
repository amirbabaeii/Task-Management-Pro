<?php

namespace App\Http\Controllers;

use App\Actions\BoardColumns\CreateBoardColumnAction;
use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Actions\BoardColumns\ReorderBoardColumnsAction;
use App\Actions\BoardColumns\UpdateBoardColumnLabelAction;
use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BoardColumnController extends Controller
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureUserHasDefaultBoard,
        private readonly EnsureBoardHasDefaultColumnsAction $ensureBoardHasDefaultColumns,
        private readonly CreateBoardColumnAction $createColumn,
        private readonly ReorderBoardColumnsAction $reorderColumns,
        private readonly UpdateBoardColumnLabelAction $updateColumnLabel,
    ) {}

    public function store(Request $request, Board $board): RedirectResponse
    {
        $board = $this->resolveBoard($request->user(), $board);

        $validated = validator(
            ['label' => trim((string) $request->input('label'))],
            ['label' => ['required', 'string', 'max:40']],
        )->validate();

        $this->createColumn->execute($board, $validated['label']);

        return redirect()->route('tasks.board', ['board' => $board]);
    }

    public function reorder(Request $request, Board $board, string $status): JsonResponse
    {
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
        )->after(function (Validator $validator) use ($status, $request): void {
            if ($status === $request->input('before_status')) {
                $validator->errors()->add(
                    'before_status',
                    'A column cannot be reordered relative to itself.',
                );
            }
        })->validate();

        $this->reorderColumns->execute(
            $board,
            $validated['status'],
            $validated['before_status'],
        );

        $fresh = $board->fresh();

        return response()->json([
            'statuses' => BoardColumn::statusesForBoard($fresh),
            'status_labels' => BoardColumn::labelsForBoard($fresh),
        ]);
    }

    public function updateLabel(Request $request, Board $board, string $status): JsonResponse
    {
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

        $this->updateColumnLabel->execute(
            $board,
            $validated['status'],
            $validated['label'],
        );

        return response()->json([
            'status' => $validated['status'],
            'label' => $validated['label'],
            'status_labels' => BoardColumn::labelsForBoard($board->fresh()),
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
