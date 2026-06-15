<?php

namespace App\Actions\TaskChecklistItems;

use App\Actions\Tasks\RecordTaskActivityAction;
use App\Enums\TaskActivityKind;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateTaskChecklistItemAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array{title?: string, completed?: bool}  $data
     */
    public function execute(TaskChecklistItem $item, array $data, ?User $actor = null): TaskChecklistItem
    {
        return DB::transaction(function () use ($item, $data, $actor): TaskChecklistItem {
            $task = $item->task()->firstOrFail();
            $originalTitle = $item->title;
            $wasCompleted = $item->completed_at !== null;

            if (array_key_exists('title', $data)) {
                $item->title = $data['title'];
            }

            if (array_key_exists('completed', $data)) {
                $item->completed_at = $data['completed'] ? now() : null;
            }

            $item->save();

            if ($originalTitle !== $item->title) {
                $this->recordActivity->execute(
                    $task,
                    TaskActivityKind::ChecklistItemRenamed,
                    [
                        'from' => $originalTitle,
                        'to' => $item->title,
                    ],
                    $actor,
                );
            }

            $isCompleted = $item->completed_at !== null;

            if ($wasCompleted !== $isCompleted) {
                $this->recordActivity->execute(
                    $task,
                    $isCompleted
                        ? TaskActivityKind::ChecklistItemCompleted
                        : TaskActivityKind::ChecklistItemReopened,
                    ['title' => $item->title],
                    $actor,
                );
            }

            return $item;
        });
    }
}
