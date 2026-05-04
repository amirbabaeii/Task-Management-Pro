<?php

namespace App\Actions\Tasks;

use App\Models\Task;

class DeleteTaskAction
{
    /**
     * Hard-delete the task. Cascading FKs on task_user and task_comments
     * clean up associated rows.
     */
    public function execute(Task $task): void
    {
        $task->delete();
    }
}
