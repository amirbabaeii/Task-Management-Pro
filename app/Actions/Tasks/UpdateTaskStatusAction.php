<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTaskStatusAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(Board $board, Task $task, string $destinationStatus, ?User $actor = null): Task
    {
        $originalStatus = $task->status;

        DB::transaction(function () use ($board, $task, $originalStatus, $destinationStatus, $actor): void {
            $task->status = $destinationStatus;
            $task->save();

            if ($originalStatus !== $destinationStatus) {
                $this->moveBetweenStatuses->execute(
                    $task,
                    $originalStatus,
                    $destinationStatus,
                    $board->id,
                );

                $this->recordActivity->execute(
                    $task,
                    TaskActivityKind::StatusChanged,
                    ['from' => $originalStatus, 'to' => $destinationStatus],
                    $actor,
                );
            }
        });

        return $task;
    }
}
