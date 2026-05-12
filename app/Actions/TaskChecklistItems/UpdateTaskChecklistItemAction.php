<?php

namespace App\Actions\TaskChecklistItems;

use App\Models\TaskChecklistItem;

class UpdateTaskChecklistItemAction
{
    /**
     * @param  array{title?: string, completed?: bool}  $data
     */
    public function execute(TaskChecklistItem $item, array $data): TaskChecklistItem
    {
        if (array_key_exists('title', $data)) {
            $item->title = $data['title'];
        }

        if (array_key_exists('completed', $data)) {
            $item->completed_at = $data['completed'] ? now() : null;
        }

        $item->save();

        return $item;
    }
}
