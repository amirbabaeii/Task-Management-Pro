<?php

namespace App\Actions\Boards;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Models\Board;
use App\Models\User;

class EnsureUserHasDefaultBoardAction
{
    public function __construct(
        private readonly EnsureBoardHasDefaultColumnsAction $ensureColumns,
    ) {}

    public function execute(User $user): Board
    {
        $board = $user->boards()
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        if (! $board) {
            $board = $user->boards()->create([
                'name' => 'My Board',
                'description' => Board::DEFAULT_DESCRIPTION,
                'position' => 1,
            ]);
        }

        $this->ensureColumns->execute($board);

        return $board;
    }
}
