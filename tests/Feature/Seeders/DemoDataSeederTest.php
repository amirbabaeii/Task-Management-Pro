<?php

namespace Tests\Feature\Seeders;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_creates_users_boards_and_tasks(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThanOrEqual(6, User::query()->count());
        $this->assertGreaterThanOrEqual(4, Board::query()->count());
        $this->assertGreaterThan(0, Task::query()->count());

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertDatabaseHas('board_members', [
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('board_members', [
            'role' => 'collaborator',
        ]);
        $this->assertDatabaseHas('task_user', [
            'role' => 'assignee',
        ]);

        $this->assertGreaterThan(
            0,
            DB::table('task_user')
                ->whereNotNull('board_id')
                ->where('role', 'assignee')
                ->count(),
        );
    }

    public function test_demo_data_seeder_does_not_duplicate_board_tasks(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $taskCount = Task::query()->count();
        $assignmentCount = DB::table('task_user')
            ->where('role', 'assignee')
            ->count();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($taskCount, Task::query()->count());
        $this->assertSame(
            $assignmentCount,
            DB::table('task_user')
                ->where('role', 'assignee')
                ->count(),
        );
    }
}
