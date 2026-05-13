<?php

namespace Tests\Feature;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\TaskStatus;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskDeadlineReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_sends_due_task_reminders_once_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 08:00:00'));

        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $dueToday = Task::factory()->create([
            'title' => 'Review launch copy',
            'status' => TaskStatus::Pending->value,
            'deadline_at' => Carbon::parse('2026-05-13 16:00:00'),
        ]);
        $overdue = Task::factory()->create([
            'title' => 'Close support loop',
            'status' => TaskStatus::InProgress->value,
            'deadline_at' => Carbon::parse('2026-05-12 09:00:00'),
        ]);
        $future = Task::factory()->create([
            'status' => TaskStatus::Pending->value,
            'deadline_at' => Carbon::parse('2026-05-14 09:00:00'),
        ]);
        $completed = Task::factory()->create([
            'status' => TaskStatus::Completed->value,
            'deadline_at' => Carbon::parse('2026-05-13 12:00:00'),
        ]);
        $archived = Task::factory()->create([
            'status' => TaskStatus::Pending->value,
            'deadline_at' => Carbon::parse('2026-05-13 12:00:00'),
            'archived_at' => Carbon::parse('2026-05-13 07:00:00'),
        ]);

        foreach ([$dueToday, $overdue, $future, $completed, $archived] as $index => $task) {
            $this->attachAssignee($user, $board, $task, $index + 1);
        }

        $this->artisan('tasks:send-due-reminders')
            ->expectsOutput('Sent 2 task deadline reminders.')
            ->assertSuccessful();

        $this->assertDatabaseHas('task_due_reminders', [
            'task_id' => $dueToday->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'reminder_date' => '2026-05-13',
        ]);
        $this->assertDatabaseHas('task_due_reminders', [
            'task_id' => $overdue->id,
            'user_id' => $user->id,
            'board_id' => $board->id,
            'reminder_date' => '2026-05-13',
        ]);
        $this->assertDatabaseCount('task_due_reminders', 2);

        $notifications = $user->notifications()
            ->where('type', TaskDeadlineReminderNotification::class)
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing(
            ['Review launch copy', 'Close support loop'],
            $notifications->pluck('data.task.title')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['today', 'overdue'],
            $notifications->pluck('data.deadline_state')->all(),
        );

        $this->artisan('tasks:send-due-reminders')
            ->expectsOutput('Sent 0 task deadline reminders.')
            ->assertSuccessful();

        $this->assertDatabaseCount('task_due_reminders', 2);
        $this->assertSame(
            2,
            $user->notifications()
                ->where('type', TaskDeadlineReminderNotification::class)
                ->count(),
        );
    }

    public function test_command_can_run_for_a_specific_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 08:00:00'));

        $user = User::factory()->create();
        $board = $this->boardFor($user);
        $task = Task::factory()->create([
            'status' => TaskStatus::Pending->value,
            'deadline_at' => Carbon::parse('2026-05-14 09:00:00'),
        ]);
        $this->attachAssignee($user, $board, $task);

        $this->artisan('tasks:send-due-reminders --date=2026-05-14')
            ->expectsOutput('Sent 1 task deadline reminder.')
            ->assertSuccessful();

        $this->assertDatabaseHas('task_due_reminders', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'reminder_date' => '2026-05-14',
        ]);
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }

    private function attachAssignee(
        User $user,
        Board $board,
        Task $task,
        int $sortOrder = 1,
    ): void {
        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => $sortOrder,
        ]);
    }
}
