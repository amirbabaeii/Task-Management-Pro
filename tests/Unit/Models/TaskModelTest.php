<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = Task::factory()->create();
    }

    public function test_task_has_users_relationship()
    {
        $user = User::factory()->create();
        $this->task->users()->attach($user->id, ['role' => 'assignee']);

        $this->assertTrue($this->task->users->contains($user));
        $this->assertEquals(1, $this->task->users->count());
    }

    public function test_task_has_assignees_relationship()
    {
        $assignee = User::factory()->create();
        $reviewer = User::factory()->create();

        $this->task->users()->attach([
            $assignee->id => ['role' => 'assignee'],
            $reviewer->id => ['role' => 'reviewer']
        ]);

        $this->assertTrue($this->task->assignees->contains($assignee));
        $this->assertFalse($this->task->assignees->contains($reviewer));
        $this->assertEquals(1, $this->task->assignees->count());
    }

    public function test_task_has_reviewers_relationship()
    {
        $assignee = User::factory()->create();
        $reviewer = User::factory()->create();

        $this->task->users()->attach([
            $assignee->id => ['role' => 'assignee'],
            $reviewer->id => ['role' => 'reviewer']
        ]);

        $this->assertTrue($this->task->reviewers->contains($reviewer));
        $this->assertFalse($this->task->reviewers->contains($assignee));
        $this->assertEquals(1, $this->task->reviewers->count());
    }

    public function test_task_can_have_multiple_assignees()
    {
        $assignees = User::factory()->count(3)->create();
        
        foreach ($assignees as $assignee) {
            $this->task->users()->attach($assignee->id, ['role' => 'assignee']);
        }

        $this->assertEquals(3, $this->task->assignees->count());
        $this->assertEquals(0, $this->task->reviewers->count());
    }

    public function test_task_can_have_multiple_reviewers()
    {
        $reviewers = User::factory()->count(3)->create();
        
        foreach ($reviewers as $reviewer) {
            $this->task->users()->attach($reviewer->id, ['role' => 'reviewer']);
        }

        $this->assertEquals(3, $this->task->reviewers->count());
        $this->assertEquals(0, $this->task->assignees->count());
    }
} 