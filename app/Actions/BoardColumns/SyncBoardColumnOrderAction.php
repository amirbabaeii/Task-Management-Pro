<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;

class SyncBoardColumnOrderAction
{
    /**
     * @param  list<string>  $statuses
     */
    public function execute(Board $board, array $statuses): void
    {
        $timestamp = now();

        foreach (array_values($statuses) as $index => $status) {
            BoardColumn::query()
                ->where('board_id', $board->id)
                ->where('status', $status)
                ->update([
                    'position' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }
}
