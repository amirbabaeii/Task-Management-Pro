<?php

namespace App\Actions\BoardColumns;

use App\Actions\Tasks\MoveTaskBetweenStatusesAction;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteBoardColumnAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
    ) {}

    /**
     * Delete a column from the board. If the column has tasks assigned on this
     * board, $moveTasksTo must be provided — those tasks are reassigned to the
     * destination status before the column is removed. The remaining columns
     * are repacked so positions stay gapless.
     */
    public function execute(
        Board $board,
        string $status,
        ?string $moveTasksTo = null,
    ): void {
        DB::transaction(function () use ($board, $status, $moveTasksTo): void {
            $assignedTaskIds = DB::table('task_user')
                ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
                ->where('task_user.board_id', $board->id)
                ->where('task_user.role', 'assignee')
                ->where('tasks.status', $status)
                ->pluck('tasks.id')
                ->unique()
                ->all();

            if ($assignedTaskIds !== []) {
                if ($moveTasksTo === null) {
                    throw new RuntimeException(
                        'Cannot delete a column with tasks; specify a destination.',
                    );
                }

                Task::query()
                    ->whereIn('id', $assignedTaskIds)
                    ->get()
                    ->each(function (Task $task) use ($status, $moveTasksTo, $board): void {
                        $task->status = $moveTasksTo;
                        $task->save();

                        $this->moveBetweenStatuses->execute(
                            $task,
                            $status,
                            $moveTasksTo,
                            $board->id,
                        );
                    });
            }

            BoardColumn::query()
                ->where('board_id', $board->id)
                ->where('status', $status)
                ->delete();

            $board->columns()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->each(function (BoardColumn $column, int $index): void {
                    $column->update(['position' => $index + 1]);
                });
        });
    }
}
