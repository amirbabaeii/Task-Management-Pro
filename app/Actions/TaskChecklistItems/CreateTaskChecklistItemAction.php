<?php

namespace App\Actions\TaskChecklistItems;

use App\Actions\Tasks\RecordTaskActivityAction;
use App\Enums\TaskActivityKind;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

class CreateTaskChecklistItemAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array{title: string}  $data
     */
    public function execute(Task $task, array $data): TaskChecklistItem
    {
        return DB::transaction(function () use ($task, $data): TaskChecklistItem {
            $position = ((int) $task->checklistItems()->max('position')) + 1;

            $item = $task->checklistItems()->create([
                'title' => $data['title'],
                'position' => $position,
            ]);

            $this->recordActivity->execute(
                $task,
                TaskActivityKind::ChecklistItemAdded,
                ['title' => $item->title],
            );

            return $item;
        });
    }
}
