<?php

namespace App\Support\Presenters;

use App\Enums\TaskStatus;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardPresenter
{
    /**
     * @return array{
     *     summary: array<string, int>,
     *     boards: list<array<string, mixed>>,
     *     upcoming_tasks: list<array<string, mixed>>
     * }
     */
    public static function forUser(User $user): array
    {
        $boards = Board::accessibleForUser($user);
        $boardIds = $boards->pluck('id')->map(fn (int $id): int => $id)->all();

        return [
            'summary' => self::taskStatsForUser($user, $boardIds),
            'boards' => self::boardsForUser($boards, $user),
            'upcoming_tasks' => self::upcomingTasksForUser($user, $boards, $boardIds),
        ];
    }

    /**
     * @param  list<int>  $boardIds
     * @return array{total_tasks: int, active_tasks: int, completed_tasks: int, overdue_tasks: int, due_today_tasks: int, due_soon_tasks: int}
     */
    private static function taskStatsForUser(User $user, array $boardIds): array
    {
        if ($boardIds === []) {
            return self::zeroStats();
        }

        $row = self::baseTaskStatsQuery($user->id, $boardIds)->first();

        return self::statsFromRow($row);
    }

    /**
     * @param  Collection<int, Board>  $boards
     * @return list<array<string, mixed>>
     */
    private static function boardsForUser(Collection $boards, User $user): array
    {
        if ($boards->isEmpty()) {
            return [];
        }

        $boardIds = $boards->pluck('id')->map(fn (int $id): int => $id)->all();
        $statsByBoard = self::baseTaskStatsQuery($user->id, $boardIds)
            ->addSelect('task_user.board_id')
            ->groupBy('task_user.board_id')
            ->get()
            ->keyBy('board_id');

        return $boards
            ->map(function (Board $board) use ($statsByBoard, $user): array {
                $stats = self::statsFromRow($statsByBoard->get($board->id));

                return [
                    ...BoardPresenter::navigation($board, $user),
                    'task_counts' => $stats,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Board>  $boards
     * @param  list<int>  $boardIds
     * @return list<array<string, mixed>>
     */
    private static function upcomingTasksForUser(User $user, Collection $boards, array $boardIds): array
    {
        if ($boardIds === []) {
            return [];
        }

        $boardsById = $boards->keyBy('id');
        $completed = TaskStatus::Completed->value;

        return $user->assignedTasks()
            ->wherePivotIn('board_id', $boardIds)
            ->where('tasks.status', '!=', $completed)
            ->whereNotNull('tasks.deadline_at')
            ->orderBy('tasks.deadline_at')
            ->orderBy('tasks.id')
            ->limit(8)
            ->get([
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.priority',
                'tasks.progress',
                'tasks.deadline_at',
            ])
            ->map(function (Task $task) use ($boardsById): array {
                $board = $boardsById->get((int) $task->pivot->board_id);

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => self::enumValue($task->priority),
                    'progress' => $task->progress,
                    'deadline_at' => $task->deadline_at,
                    'deadline_state' => self::deadlineState($task),
                    'board' => $board
                        ? [
                            'id' => $board->id,
                            'name' => $board->name,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $boardIds
     */
    private static function baseTaskStatsQuery(int $userId, array $boardIds): \Illuminate\Database\Query\Builder
    {
        $completed = TaskStatus::Completed->value;
        $todayStart = now()->startOfDay();
        $todayEnd = $todayStart->copy()->endOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $weekEnd = $todayStart->copy()->addDays(7)->endOfDay();

        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.role', 'assignee')
            ->whereIn('task_user.board_id', $boardIds)
            ->selectRaw('COUNT(*) as total_tasks')
            ->selectRaw('SUM(CASE WHEN tasks.status != ? THEN 1 ELSE 0 END) as active_tasks', [$completed])
            ->selectRaw('SUM(CASE WHEN tasks.status = ? THEN 1 ELSE 0 END) as completed_tasks', [$completed])
            ->selectRaw('SUM(CASE WHEN tasks.status != ? AND tasks.deadline_at < ? THEN 1 ELSE 0 END) as overdue_tasks', [$completed, $todayStart])
            ->selectRaw('SUM(CASE WHEN tasks.status != ? AND tasks.deadline_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_today_tasks', [$completed, $todayStart, $todayEnd])
            ->selectRaw('SUM(CASE WHEN tasks.status != ? AND tasks.deadline_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_soon_tasks', [$completed, $tomorrowStart, $weekEnd]);
    }

    /**
     * @return array{total_tasks: int, active_tasks: int, completed_tasks: int, overdue_tasks: int, due_today_tasks: int, due_soon_tasks: int}
     */
    private static function statsFromRow(?object $row): array
    {
        if (! $row) {
            return self::zeroStats();
        }

        return [
            'total_tasks' => (int) $row->total_tasks,
            'active_tasks' => (int) $row->active_tasks,
            'completed_tasks' => (int) $row->completed_tasks,
            'overdue_tasks' => (int) $row->overdue_tasks,
            'due_today_tasks' => (int) $row->due_today_tasks,
            'due_soon_tasks' => (int) $row->due_soon_tasks,
        ];
    }

    /**
     * @return array{total_tasks: int, active_tasks: int, completed_tasks: int, overdue_tasks: int, due_today_tasks: int, due_soon_tasks: int}
     */
    private static function zeroStats(): array
    {
        return [
            'total_tasks' => 0,
            'active_tasks' => 0,
            'completed_tasks' => 0,
            'overdue_tasks' => 0,
            'due_today_tasks' => 0,
            'due_soon_tasks' => 0,
        ];
    }

    private static function deadlineState(Task $task): string
    {
        if (! $task->deadline_at) {
            return 'none';
        }

        $deadline = $task->deadline_at;
        $todayStart = now()->startOfDay();

        if ($deadline->lt($todayStart)) {
            return 'overdue';
        }

        if ($deadline->isSameDay($todayStart)) {
            return 'today';
        }

        if ($deadline->lte($todayStart->copy()->addDays(7)->endOfDay())) {
            return 'soon';
        }

        return 'scheduled';
    }

    private static function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
