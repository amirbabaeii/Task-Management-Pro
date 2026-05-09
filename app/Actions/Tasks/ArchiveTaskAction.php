<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveTaskAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor): Task {
            if ($task->archived_at !== null) {
                return $task;
            }

            $task->forceFill(['archived_at' => now()]);
            $task->save();

            $this->recordActivity->execute(
                $task,
                TaskActivityKind::Archived,
                [],
                $actor,
            );

            return $task;
        });
    }
}
