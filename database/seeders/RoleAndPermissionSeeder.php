<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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
        ];

        foreach ($permissions as $permission) {
            $normalUser->givePermissionTo($permission);
        }

        $vipUser->givePermissionTo(array_merge($permissions, ['use-ai']));

        $admin->givePermissionTo(array_merge($permissions, ['use-ai', 'see-users', 'add-users', 'edit-users', 'delete-users']));
        
    }
} 