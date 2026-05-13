<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Board;
use App\Models\Task;
use App\Models\TaskDueReminder;
use App\Models\User;
use App\Notifications\TaskDeadlineReminderNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendTaskDueRemindersAction
{
    public function execute(?CarbonInterface $date = null): int
    {
        $runDate = ($date ?? now())->copy()->startOfDay();
        $reminderDate = $runDate->toDateString();
        $assignments = $this->dueAssignments($runDate->copy()->endOfDay());

        if ($assignments->isEmpty()) {
            return 0;
        }

        $users = User::query()
            ->whereIn('id', $assignments->pluck('user_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $tasks = Task::query()
            ->whereIn('id', $assignments->pluck('task_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $boards = Board::query()
            ->whereIn('id', $assignments->pluck('board_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $sent = 0;

        foreach ($assignments as $assignment) {
            $user = $users->get((int) $assignment->user_id);
            $task = $tasks->get((int) $assignment->task_id);
            $board = $boards->get((int) $assignment->board_id);

            if (! $user || ! $task || ! $board) {
                continue;
            }

            $reminder = TaskDueReminder::query()->firstOrCreate(
                [
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'reminder_date' => $reminderDate,
                ],
                [
                    'board_id' => $board->id,
                ],
            );

            if (! $reminder->wasRecentlyCreated) {
                continue;
            }

            Notification::send(
                $user,
                new TaskDeadlineReminderNotification(
                    $task,
                    $board,
                    $this->deadlineState($task, $runDate),
                    $runDate,
                ),
            );

            $sent++;
        }

        return $sent;
    }

    /**
     * @return Collection<int, object{task_id: int, user_id: int, board_id: int}>
     */
    private function dueAssignments(CarbonInterface $through): Collection
    {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.role', 'assignee')
            ->whereNotNull('task_user.board_id')
            ->whereNull('tasks.archived_at')
            ->where('tasks.status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('tasks.deadline_at')
            ->where('tasks.deadline_at', '<=', $through)
            ->select([
                'task_user.task_id',
                'task_user.user_id',
                'task_user.board_id',
            ])
            ->orderBy('tasks.deadline_at')
            ->orderBy('task_user.task_id')
            ->orderBy('task_user.user_id')
            ->get();
    }

    private function deadlineState(Task $task, CarbonInterface $runDate): string
    {
        if (! $task->deadline_at) {
            return 'none';
        }

        return $task->deadline_at->lt($runDate)
            ? 'overdue'
            : 'today';
    }
}
