<?php
// database/tests/Unit/RolePermissionTest.php

namespace Tests\Unit\Database;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;
    public function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    
    public function test_normal_user_permissions()
    {
        $normalUser = Role::findByName('normal-user');
        $this->assertTrue($normalUser->hasPermissionTo('see-tasks'));
        $this->assertTrue($normalUser->hasPermissionTo('create-tasks'));
        $this->assertTrue($normalUser->hasPermissionTo('edit-tasks'));
        $this->assertTrue($normalUser->hasPermissionTo('delete-tasks'));
        $this->assertFalse($normalUser->hasPermissionTo('use-ai'));
        $this->assertFalse($normalUser->hasPermissionTo('see-users'));
    }

    public function test_vip_user_permissions()
    {
        $vipUser = Role::findByName('vip-user');
        $this->assertTrue($vipUser->hasPermissionTo('see-tasks'));
        $this->assertTrue($vipUser->hasPermissionTo('create-tasks'));
        $this->assertTrue($vipUser->hasPermissionTo('edit-tasks'));
        $this->assertTrue($vipUser->hasPermissionTo('delete-tasks'));
        $this->assertTrue($vipUser->hasPermissionTo('use-ai'));
        $this->assertFalse($vipUser->hasPermissionTo('see-users'));
    }

    public function test_admin_permissions()
    {
        $admin = Role::findByName('admin');
        $this->assertTrue($admin->hasPermissionTo('see-tasks'));
        $this->assertTrue($admin->hasPermissionTo('create-tasks'));
        $this->assertTrue($admin->hasPermissionTo('edit-tasks'));
        $this->assertTrue($admin->hasPermissionTo('delete-tasks'));
        $this->assertTrue($admin->hasPermissionTo('use-ai'));
        $this->assertTrue($admin->hasPermissionTo('see-users'));
        $this->assertTrue($admin->hasPermissionTo('add-users'));
        $this->assertTrue($admin->hasPermissionTo('edit-users'));
        $this->assertTrue($admin->hasPermissionTo('delete-users'));
    }
}