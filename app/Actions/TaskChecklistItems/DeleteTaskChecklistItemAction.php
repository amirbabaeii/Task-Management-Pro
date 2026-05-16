<?php

namespace App\Actions\TaskChecklistItems;

use App\Actions\Tasks\RecordTaskActivityAction;
use App\Enums\TaskActivityKind;
use App\Models\TaskChecklistItem;
use Illuminate\Support\Facades\DB;

class DeleteTaskChecklistItemAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(TaskChecklistItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $task = $item->task()->firstOrFail();
            $title = $item->title;

            $item->delete();

            $this->recordActivity->execute(
                $task,
                TaskActivityKind::ChecklistItemDeleted,
                ['title' => $title],
            );
        });
    }
}
