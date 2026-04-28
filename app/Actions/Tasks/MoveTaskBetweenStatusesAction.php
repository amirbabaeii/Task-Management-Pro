<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * When a task changes status, every assignee's per-user task ordering needs
 * to be normalized: remove the task from its old column for each assignee,
 * append it to the destination column, and ensure the destination column
 * exists on each assignee's board (covers cross-board reassignments).
 */
class MoveTaskBetweenStatusesAction
{
    public function execute(
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

            BoardTaskAssignments::syncOrder(
                $userId,
                $boardId,
                BoardTaskAssignments::assignedTaskIdsForStatus(
                    $userId,
                    $boardId,
                    $sourceStatus,
                    $task->id,
                ),
            );

            $this->ensureColumnExistsForStatus($boardId, $destinationStatus, $sourceBoardId);

            $destinationTaskIds = BoardTaskAssignments::assignedTaskIdsForStatus(
                $userId,
                $boardId,
                $destinationStatus,
                $task->id,
            );

            $destinationTaskIds[] = $task->id;

            BoardTaskAssignments::syncOrder($userId, $boardId, $destinationTaskIds);
        }
    }

    /**
     * If the destination status doesn't exist as a column on the target board,
     * create it — borrowing the label from the source board first, then any
     * existing column, then deriving from the status slug.
     */
    private function ensureColumnExistsForStatus(
        int $targetBoardId,
        string $status,
        int $sourceBoardId,
    ): void {
        $exists = BoardColumn::query()
            ->where('board_id', $targetBoardId)
            ->where('status', $status)
            ->exists();

        if ($exists) {
            return;
        }

        $sourceBoard = Board::query()->findOrFail($sourceBoardId);
        $label = $sourceBoard->columns()->where('status', $status)->value('label')
            ?? BoardColumn::query()->where('status', $status)->value('label')
            ?? (string) Str::of($status)->replace('-', ' ')->title();

        $targetBoard = Board::query()->findOrFail($targetBoardId);

        BoardColumn::query()->create([
            'user_id' => $targetBoard->user_id,
            'board_id' => $targetBoard->id,
            'status' => $status,
            'label' => trim($label),
            'position' => BoardColumn::nextPositionForBoard($targetBoard),
        ]);
    }
}
