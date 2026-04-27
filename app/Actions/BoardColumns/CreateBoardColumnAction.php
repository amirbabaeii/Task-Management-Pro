<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Support\Str;

class CreateBoardColumnAction
{
    public function execute(Board $board, string $label): BoardColumn
    {
        return BoardColumn::query()->create([
            'user_id' => $board->user_id,
            'board_id' => $board->id,
            'status' => 'column-'.Str::lower((string) Str::ulid()),
            'label' => $label,
            'position' => BoardColumn::nextPositionForBoard($board),
        ]);
    }
}
