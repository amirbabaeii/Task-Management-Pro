<?php

namespace App\Actions\Boards;

use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\User;
use RuntimeException;

class AddBoardMemberAction
{
    /**
     * Attach a user to the board as a collaborator. The owner row is created
     * automatically on Board::created and never replayed here.
     *
     * Idempotent — calling twice with the same user is a no-op.
     */
    public function execute(Board $board, User $user, BoardRole $role = BoardRole::Collaborator): void
    {
        if ($role === BoardRole::Owner) {
            throw new RuntimeException(
                'Use board ownership transfer to assign the owner role.',
            );
        }

        if ($board->isOwnedBy($user)) {
            // Owner already has a membership row; don't downgrade them.
            return;
        }

        $board->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role->value,
                'joined_at' => now(),
            ],
        ]);
    }
}
