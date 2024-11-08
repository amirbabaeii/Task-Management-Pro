<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run()
    {
        $normalUser = Role::create(['name' => 'normal-user']);
        $vipUser = Role::create(['name' => 'vip-user']);
        $admin = Role::create(['name' => 'admin']);

        $permissions = [
            'see-tasks',
            'create-tasks',
            'edit-tasks',
            'delete-tasks',
            'use-ai',
            'see-users',
            'add-users',
            'edit-users',
            'delete-users',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }
        $normal_user_permissions = ['see-tasks', 'create-tasks', 'edit-tasks', 'delete-tasks'];
        $normalUser->givePermissionTo($normal_user_permissions);
        $vipUser->givePermissionTo(array_merge($normal_user_permissions, ['use-ai']));
        $admin->givePermissionTo(array_merge($normal_user_permissions, ['use-ai', 'see-users', 'add-users', 'edit-users', 'delete-users']));
    }
} 