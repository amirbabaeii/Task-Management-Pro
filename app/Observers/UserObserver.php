<?php

namespace App\Observers;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Models\User;

class UserObserver
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureDefaultBoard,
    ) {}

    public function created(User $user): void
    {
        $this->ensureDefaultBoard->execute($user);
    }
}
