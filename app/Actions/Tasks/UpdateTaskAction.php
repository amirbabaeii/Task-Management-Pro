<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UpdateTaskAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Board $board, Task $task, array $data): Task
    {
        $originalStatus = $task->status;

        DB::transaction(function () use ($board, $task, $data, $originalStatus): void {
            $task->fill($data);
            $task->save();

            if ($originalStatus !== $task->status) {
                $this->moveBetweenStatuses->execute(
                    $task,
                    $originalStatus,
                    $task->status,
                    $board->id,
                );
            }
        });

        return $task;
    }
}
