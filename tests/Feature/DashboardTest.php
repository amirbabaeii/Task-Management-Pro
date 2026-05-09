<?php

namespace Tests\Feature;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_summarizes_owned_and_shared_work(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 09:00:00'));

        $user = User::factory()->create();
        $sharedOwner = User::factory()->create();
        $ownedBoard = $this->boardFor($user);
        $sharedBoard = $this->boardFor($sharedOwner);
        $ownedBoard->update(['name' => 'Launch Board']);
        $sharedBoard->update(['name' => 'Shared Ops']);
        $sharedBoard->members()->attach($user->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $overdueTask = Task::factory()->create([
            'title' => 'Send launch notes',
            'status' => 'pending',
            'priority' => 'high',
            'progress' => 20,
            'deadline_at' => Carbon::parse('2026-05-09 12:00:00'),
        ]);
        $todayTask = Task::factory()->create([
            'title' => 'Review support queue',
            'status' => 'in-progress',
            'priority' => 'medium',
            'progress' => 50,
            'deadline_at' => Carbon::parse('2026-05-10 14:00:00'),
        ]);
        $soonTask = Task::factory()->create([
            'title' => 'Draft release checklist',
            'status' => 'pending',
            'priority' => 'low',
            'progress' => 0,
            'deadline_at' => Carbon::parse('2026-05-13 09:00:00'),
        ]);
        $completedTask = Task::factory()->create([
            'title' => 'Close launch prep',
            'status' => 'completed',
            'priority' => 'medium',
            'progress' => 100,
            'deadline_at' => Carbon::parse('2026-05-08 09:00:00'),
        ]);
        $archivedTask = Task::factory()->create([
            'title' => 'Archived follow-up',
            'status' => 'pending',
            'priority' => 'high',
            'progress' => 10,
            'deadline_at' => Carbon::parse('2026-05-11 09:00:00'),
            'archived_at' => Carbon::parse('2026-05-10 08:00:00'),
        ]);
        $foreignTask = Task::factory()->create([
            'title' => 'Invisible task',
            'status' => 'pending',
            'deadline_at' => Carbon::parse('2026-05-11 09:00:00'),
        ]);

        $this->attachAssignee($user, $ownedBoard, $overdueTask, 1);
        $this->attachAssignee($user, $sharedBoard, $todayTask, 1);
        $this->attachAssignee($user, $ownedBoard, $soonTask, 2);
        $this->attachAssignee($user, $ownedBoard, $completedTask, 3);
        $this->attachAssignee($user, $ownedBoard, $archivedTask, 4);
        $this->attachAssignee($sharedOwner, $sharedBoard, $foreignTask, 2);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('dashboard.summary.total_tasks', 4)
                ->where('dashboard.summary.active_tasks', 3)
                ->where('dashboard.summary.completed_tasks', 1)
                ->where('dashboard.summary.overdue_tasks', 1)
                ->where('dashboard.summary.due_today_tasks', 1)
                ->where('dashboard.summary.due_soon_tasks', 1)
                ->has('dashboard.boards', 2)
                ->where('dashboard.boards.0.id', $ownedBoard->id)
                ->where('dashboard.boards.0.task_counts.total_tasks', 3)
                ->where('dashboard.boards.1.id', $sharedBoard->id)
                ->where('dashboard.boards.1.role', BoardRole::Collaborator->value)
                ->where('dashboard.boards.1.task_counts.total_tasks', 1)
                ->has('dashboard.upcoming_tasks', 3)
                ->where('dashboard.upcoming_tasks.0.id', $overdueTask->id)
                ->where('dashboard.upcoming_tasks.0.deadline_state', 'overdue')
                ->where('dashboard.upcoming_tasks.1.id', $todayTask->id)
                ->where('dashboard.upcoming_tasks.1.board.id', $sharedBoard->id)
                ->where('dashboard.upcoming_tasks.2.id', $soonTask->id));
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }

    private function attachAssignee(
        User $user,
        Board $board,
        Task $task,
        int $sortOrder,
    ): void {
        $task->users()->attach($user->id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => $sortOrder,
        ]);
    }
}
