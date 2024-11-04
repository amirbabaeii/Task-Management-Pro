<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_has_tasks_relationship()
    {
        $task = Task::factory()->create();
        $this->user->tasks()->attach($task->id, ['role' => 'assignee']);

        $this->assertTrue($this->user->tasks->contains($task));
        $this->assertEquals(1, $this->user->tasks->count());
    }

    public function test_user_has_assigned_tasks_relationship()
    {
        $assignedTask = Task::factory()->create();
        $reviewTask = Task::factory()->create();

        $this->user->tasks()->attach([
            $assignedTask->id => ['role' => 'assignee'],
            $reviewTask->id => ['role' => 'reviewer']
        ]);

        $this->assertTrue($this->user->assignedTasks->contains($assignedTask));
        $this->assertFalse($this->user->assignedTasks->contains($reviewTask));
        $this->assertEquals(1, $this->user->assignedTasks->count());
    }

    public function test_user_has_reviewing_tasks_relationship()
    {
        $assignedTask = Task::factory()->create();
        $reviewTask = Task::factory()->create();

        $this->user->tasks()->attach([
            $assignedTask->id => ['role' => 'assignee'],
            $reviewTask->id => ['role' => 'reviewer']
        ]);

        $this->assertTrue($this->user->reviewingTasks->contains($reviewTask));
        $this->assertFalse($this->user->reviewingTasks->contains($assignedTask));
        $this->assertEquals(1, $this->user->reviewingTasks->count());
    }

    public function test_user_can_be_assigned_to_multiple_tasks()
    {
        $tasks = Task::factory()->count(3)->create();
        
        foreach ($tasks as $task) {
            $this->user->tasks()->attach($task->id, ['role' => 'assignee']);
        }

        $this->assertEquals(3, $this->user->assignedTasks->count());
        $this->assertEquals(0, $this->user->reviewingTasks->count());
    }

    public function test_user_can_review_multiple_tasks()
    {
        $tasks = Task::factory()->count(3)->create();
        
        foreach ($tasks as $task) {
            $this->user->tasks()->attach($task->id, ['role' => 'reviewer']);
        }

        $this->assertEquals(3, $this->user->reviewingTasks->count());
        $this->assertEquals(0, $this->user->assignedTasks->count());
    }

    public function test_user_can_have_mixed_roles_on_different_tasks()
    {
        $assignedTasks = Task::factory()->count(2)->create();
        $reviewTasks = Task::factory()->count(2)->create();
        
        foreach ($assignedTasks as $task) {
            $this->user->tasks()->attach($task->id, ['role' => 'assignee']);
        }
        
        foreach ($reviewTasks as $task) {
            $this->user->tasks()->attach($task->id, ['role' => 'reviewer']);
        }

        $this->assertEquals(4, $this->user->tasks->count());
        $this->assertEquals(2, $this->user->assignedTasks->count());
        $this->assertEquals(2, $this->user->reviewingTasks->count());
    }
} 