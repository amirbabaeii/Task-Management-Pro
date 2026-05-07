<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

class RecordTaskActivityAction
{
    /**
     * Append an entry to the task's activity log. Falls back to the
     * authenticated user when an explicit actor isn't passed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Task $task,
        TaskActivityKind $kind,
        array $payload = [],
        ?User $actor = null,
    ): TaskActivity {
        return TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => ($actor ?? auth()->user())?->id,
            'kind' => $kind->value,
            'payload' => $payload === [] ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
