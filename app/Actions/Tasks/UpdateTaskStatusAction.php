<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UpdateTaskStatusAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
    ) {}

    public function execute(Board $board, Task $task, string $destinationStatus): Task
    {
        $originalStatus = $task->status;

        DB::transaction(function () use ($board, $task, $originalStatus, $destinationStatus): void {
            $task->status = $destinationStatus;
            $task->save();

            if ($originalStatus !== $destinationStatus) {
                $this->moveBetweenStatuses->execute(
                    $task,
                    $originalStatus,
                    $destinationStatus,
                    $board->id,
                );
            }
        });

        return $task;
    }
}
