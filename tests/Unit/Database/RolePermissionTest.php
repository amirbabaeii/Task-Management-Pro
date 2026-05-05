<?php

namespace Tests\Unit\Database;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_normal_user_has_task_permissions(): void
    {
        $normalUser = Role::findByName('normal-user');

        $this->assertTrue($normalUser->hasPermissionTo('create-tasks'));
        $this->assertTrue($normalUser->hasPermissionTo('edit-tasks'));
        $this->assertTrue($normalUser->hasPermissionTo('delete-tasks'));
    }

    public function test_admin_has_task_permissions(): void
    {
        $admin = Role::findByName('admin');

        $this->assertTrue($admin->hasPermissionTo('create-tasks'));
        $this->assertTrue($admin->hasPermissionTo('edit-tasks'));
        $this->assertTrue($admin->hasPermissionTo('delete-tasks'));
    }
}
