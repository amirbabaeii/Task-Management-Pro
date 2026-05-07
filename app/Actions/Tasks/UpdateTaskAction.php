<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UpdateTaskAction
{
    public function __construct(
        private readonly MoveTaskBetweenStatusesAction $moveBetweenStatuses,
        private readonly UpdateTaskAssigneesAction $updateAssignees,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Board $board, Task $task, array $data): Task
    {
        $originalStatus = $task->status;
        $assigneeIds = array_key_exists('assignee_ids', $data)
            ? $data['assignee_ids']
            : null;
        unset($data['assignee_ids']);

        DB::transaction(function () use ($board, $task, $data, $originalStatus, $assigneeIds): void {
            $task->fill($data);
            $task->save();

            if ($originalStatus !== $task->status) {
                $this->moveBetweenStatuses->execute(
                    $task,
                    $originalStatus,
                    $task->status,
                    $board->id,
                );

                $this->recordActivity->execute(
                    $task,
                    TaskActivityKind::StatusChanged,
                    ['from' => $originalStatus, 'to' => $task->status],
                );
            }

            if (is_array($assigneeIds)) {
                $this->updateAssignees->execute($board, $task, $assigneeIds);
            }
        });

        return $task;
    }
}
