<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RemoveBoardMemberAction
{
    /**
     * Remove a collaborator from the board. The owner cannot be removed —
     * board ownership transfer is a separate operation we don't support yet.
     *
     * Side effect: any task assignments the removed member had on this
     * board's task_user pivot are detached so the task no longer appears
     * on phantom boards for that user.
     */
    public function execute(Board $board, User $user): void
    {
        if ($board->isOwnedBy($user)) {
            throw new RuntimeException(
                'The board owner cannot be removed. Transfer ownership first.',
            );
        }

        DB::transaction(function () use ($board, $user): void {
            $board->members()->detach($user->id);

            DB::table('task_user')
                ->where('board_id', $board->id)
                ->where('user_id', $user->id)
                ->delete();
        });
    }
}
