<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;

class ReorderBoardColumnsAction
{
    public function __construct(
        private readonly SyncBoardColumnOrderAction $syncOrder,
    ) {}

    /**
     * Move $movedStatus directly before $beforeStatus, or to the end when null.
     *
     * @return list<string> the resulting status order
     */
    public function execute(Board $board, string $movedStatus, ?string $beforeStatus): array
    {
        $availableStatuses = BoardColumn::statusesForBoard($board);

        $reordered = array_values(array_filter(
            $availableStatuses,
            fn (string $status): bool => $status !== $movedStatus,
        ));

        $insertAt = $beforeStatus === null
            ? count($reordered)
            : array_search($beforeStatus, $reordered, true);

        if ($insertAt === false) {
            $insertAt = count($reordered);
        }

        array_splice($reordered, $insertAt, 0, [$movedStatus]);

        $this->syncOrder->execute($board, $reordered);

        return $reordered;
    }
}
