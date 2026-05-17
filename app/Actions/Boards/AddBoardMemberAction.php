<?php

namespace App\Actions\Boards;

use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\User;
use App\Notifications\BoardMemberAddedNotification;
use RuntimeException;

class AddBoardMemberAction
{
    /**
     * Attach a user to the board as a collaborator. The owner row is created
     * automatically on Board::created and never replayed here.
     *
     * Idempotent — calling twice with the same user is a no-op.
     */
    public function execute(
        Board $board,
        User $user,
        BoardRole $role = BoardRole::Collaborator,
        ?User $invitedBy = null,
    ): void {
        if ($role === BoardRole::Owner) {
            throw new RuntimeException(
                'Use board ownership transfer to assign the owner role.',
            );
        }

        if ($board->isOwnedBy($user)) {
            // Owner already has a membership row; don't downgrade them.
            return;
        }

        $alreadyMember = $board->hasMember($user);

        $board->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $role->value,
                'joined_at' => now(),
            ],
        ]);

        $invitedBy ??= auth()->user();

        if (! $alreadyMember && $invitedBy !== null && (int) $invitedBy->id !== (int) $user->id) {
            $user->notify(new BoardMemberAddedNotification($board, $invitedBy));
        }
    }
}
