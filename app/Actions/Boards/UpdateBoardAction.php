<?php

namespace App\Actions\Boards;

use App\Models\Board;

class UpdateBoardAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Board $board, array $data): Board
    {
        $board->update($data);

        return $board;
    }
}
