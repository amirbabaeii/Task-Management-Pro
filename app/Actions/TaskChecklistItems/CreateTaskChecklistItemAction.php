<?php

namespace App\Actions\TaskChecklistItems;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

class CreateTaskChecklistItemAction
{
    /**
     * @param  array{title: string}  $data
     */
    public function execute(Task $task, array $data): TaskChecklistItem
    {
        return DB::transaction(function () use ($task, $data): TaskChecklistItem {
            $position = ((int) $task->checklistItems()->max('position')) + 1;

            return $task->checklistItems()->create([
                'title' => $data['title'],
                'position' => $position,
            ]);
        });
    }
}
