<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class BoardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user owns the board.
     *
     * Failures are surfaced as 404 so we don't leak the existence of
     * boards belonging to other users.
     */
    public function view(User $user, Board $board): Response
    {
        return (int) $board->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Board $board): Response
    {
        return $this->view($user, $board);
    }
}
