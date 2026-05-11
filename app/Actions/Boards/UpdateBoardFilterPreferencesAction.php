<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBoardFilterPreferencesAction
{
    /**
     * @param  array{search: string, priorities: list<string>, assignee_id: int|null, deadline: string, view: string}  $preferences
     * @return array{search: string, priorities: list<string>, assignee_id: int|null, deadline: string, view: string}
     */
    public function execute(Board $board, User $user, array $preferences): array
    {
        DB::table('board_members')
            ->where('board_id', $board->id)
            ->where('user_id', $user->id)
            ->update([
                'filter_preferences' => json_encode($preferences),
                'updated_at' => now(),
            ]);

        return $preferences;
    }
}
