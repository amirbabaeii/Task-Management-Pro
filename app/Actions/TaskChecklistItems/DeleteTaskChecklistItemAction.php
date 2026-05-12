<?php

namespace App\Actions\TaskChecklistItems;

use App\Models\TaskChecklistItem;

class DeleteTaskChecklistItemAction
{
    public function execute(TaskChecklistItem $item): void
    {
        $item->delete();
    }
}
