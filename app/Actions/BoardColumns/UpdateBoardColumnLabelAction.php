<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;

class UpdateBoardColumnLabelAction
{
    public function execute(Board $board, string $status, string $label): void
    {
        $board->columns()
            ->where('status', $status)
            ->update(['label' => $label]);
    }
}
