<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;

class EnsureBoardHasDefaultColumnsAction
{
    public function execute(Board $board): void
    {
        $existingStatuses = $board->columns()
            ->pluck('status')
            ->all();

        $rows = [];
        $timestamp = now();

        foreach (BoardColumn::defaultColumns() as $index => $column) {
            if (in_array($column['status'], $existingStatuses, true)) {
                continue;
            }

            $rows[] = [
                'user_id' => $board->user_id,
                'board_id' => $board->id,
                'status' => $column['status'],
                'label' => $column['label'],
                'position' => $index + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows === []) {
            return;
        }

        BoardColumn::query()->insert($rows);
    }
}
