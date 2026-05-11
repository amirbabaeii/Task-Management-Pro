<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;

class ReorderTaskAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
    ) {}

    public function execute(
        Board $board,
        Task $task,
        string $destinationStatus,
        ?int $beforeTaskId,
    ): void {
        DB::transaction(function () use ($board, $task, $destinationStatus, $beforeTaskId): void {
            $sourceStatus = $task->status;

            if ($sourceStatus !== $destinationStatus) {
                $task->status = $destinationStatus;
                $task->save();

                $this->moveBetweenStatuses->execute(
                    $task,
                    $sourceStatus,
                    $destinationStatus,
                    $board->id,
                );
            }

            $this->placeAt($board->id, $task, $destinationStatus, $beforeTaskId);
        });
    }

    private function placeAt(
        int $boardId,
        Task $task,
        string $status,
        ?int $beforeTaskId,
    ): void {
        $taskIds = BoardTaskAssignments::taskIdsForBoardStatus(
            $boardId,
            $status,
            $task->id,
        );

        $insertAt = $beforeTaskId === null
            ? count($taskIds)
            : array_search($beforeTaskId, $taskIds, true);

        if ($insertAt === false) {
            $insertAt = count($taskIds);
        }

        array_splice($taskIds, $insertAt, 0, [$task->id]);

        BoardTaskAssignments::syncBoardOrder($boardId, $taskIds);
    }
}
