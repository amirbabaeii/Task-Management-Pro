<?php

namespace App\Support\Presenters;

use App\Models\TaskChecklistItem;

class TaskChecklistItemPresenter
{
    /**
     * @return array{id: int, title: string, completed: bool, completed_at: mixed, position: int}
     */
    public static function toArray(TaskChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'completed' => $item->completed_at !== null,
            'completed_at' => $item->completed_at,
            'position' => $item->position,
        ];
    }
}
