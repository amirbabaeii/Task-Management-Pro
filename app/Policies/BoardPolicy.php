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
     * Owners and accepted collaborators can view a board. Failures surface
     * as 404 so we don't leak the existence of boards owned by other users.
     */
    public function view(User $user, Board $board): Response
    {
        return $board->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Both owners and collaborators can edit board content (rename,
     * tasks, columns). Member-management is handled by manageMembers.
     */
    public function update(User $user, Board $board): Response
    {
        return $this->view($user, $board);
    }

    /**
     * Only the board owner can invite, remove, or otherwise alter the
     * member roster.
     */
    public function manageMembers(User $user, Board $board): Response
    {
        return $board->isOwnedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
