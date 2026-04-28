<?php

namespace App\Actions\Boards;

use App\Actions\BoardColumns\EnsureBoardHasDefaultColumnsAction;
use App\Models\Board;
use App\Models\User;

class CreateBoardAction
{
    public function __construct(
        private readonly EnsureBoardHasDefaultColumnsAction $ensureColumns,
    ) {}

    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function execute(User $owner, array $data): Board
    {
        $board = $owner->boards()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'position' => Board::nextPositionForUser($owner),
        ]);

        $this->ensureColumns->execute($board);

        return $board;
    }
}
