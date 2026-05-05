<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Permissions intentionally only cover features that exist today. Add to
     * this list as features ship — don't seed permissions for vapor.
     */
    public function run(): void
    {
        $permissions = [
            'create-tasks',
            'edit-tasks',
            'delete-tasks',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $normalUser = Role::firstOrCreate([
            'name' => 'normal-user',
            'guard_name' => 'web',
        ]);
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $normalUser->syncPermissions($permissions);
        $admin->syncPermissions($permissions);
    }
}
