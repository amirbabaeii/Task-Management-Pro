<?php

namespace App\Observers;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserObserver
{
    public function __construct(
        private readonly EnsureUserHasDefaultBoardAction $ensureDefaultBoard,
    ) {}

    public function created(User $user): void
    {
        $this->ensureDefaultBoard->execute($user);
        $this->assignDefaultRole($user);
    }

    /**
     * Give every new user the baseline role. Skipped silently if the role
     * isn't seeded yet (e.g. tests that don't run RoleAndPermissionSeeder).
     */
    private function assignDefaultRole(User $user): void
    {
        $role = Role::query()
            ->where('name', 'normal-user')
            ->where('guard_name', 'web')
            ->first();

        if ($role !== null) {
            $user->assignRole($role);
        }
    }
}
