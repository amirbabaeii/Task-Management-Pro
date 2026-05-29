<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Query helpers for the task_user pivot. Encapsulates board membership and
 * ordering of assigned tasks so callers don't have to write raw pivot SQL.
 */
class BoardTaskAssignments
{
    public static function nextSortOrderForUserStatus(int $userId, int $boardId, string $status): int
    {
        $sortOrder = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->max('task_user.sort_order');

        return ((int) $sortOrder) + 1;
    }

    public static function nextSortOrderForBoardStatus(int $boardId, string $status): int
    {
        $sortOrder = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->max('task_user.sort_order');

        return ((int) $sortOrder) + 1;
    }

    public static function sortOrderForBoardTask(int $boardId, int $taskId): int
    {
        $sortOrder = DB::table('task_user')
            ->where('board_id', $boardId)
            ->where('role', 'assignee')
            ->where('task_id', $taskId)
            ->min('sort_order');

        return (int) $sortOrder;
    }

    /**
     * @return list<int>
     */
    public static function assignedTaskIdsForStatus(
        int $userId,
        int $boardId,
        string $status,
        ?int $excludingTaskId = null,
    ): array {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->when(
                $excludingTaskId !== null,
                fn ($query) => $query->where('tasks.id', '!=', $excludingTaskId),
            )
            ->orderBy('task_user.sort_order')
            ->orderBy('tasks.id')
            ->pluck('tasks.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Bulk re-number sort_order for a user/board in the given task ID order.
     *
     * @param  list<int>  $taskIds
     */
    public static function syncOrder(int $userId, int $boardId, array $taskIds): void
    {
        $timestamp = now();

        foreach (array_values($taskIds) as $index => $taskId) {
            DB::table('task_user')
                ->where('user_id', $userId)
                ->where('board_id', $boardId)
                ->where('role', 'assignee')
                ->where('task_id', $taskId)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    /**
     * @return list<int>
     */
    public static function taskIdsForBoardStatus(
        int $boardId,
        string $status,
        ?int $excludingTaskId = null,
    ): array {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.status', $status)
            ->when(
                $excludingTaskId !== null,
                fn ($query) => $query->where('tasks.id', '!=', $excludingTaskId),
            )
            ->groupBy('tasks.id')
            ->orderByRaw('MIN(task_user.sort_order)')
            ->orderBy('tasks.id')
            ->pluck('tasks.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Bulk re-number sort_order for every assignee row on a board.
     *
     * @param  list<int>  $taskIds
     */
    public static function syncBoardOrder(int $boardId, array $taskIds): void
    {
        $timestamp = now();

        foreach (array_values($taskIds) as $index => $taskId) {
            DB::table('task_user')
                ->where('board_id', $boardId)
                ->where('role', 'assignee')
                ->where('task_id', $taskId)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    public static function userHasTaskOnBoard(int $userId, int $boardId, int $taskId): bool
    {
        return DB::table('task_user')
            ->where('user_id', $userId)
            ->where('board_id', $boardId)
            ->where('role', 'assignee')
            ->where('task_id', $taskId)
            ->exists();
    }

    public static function taskExistsOnBoard(int $boardId, int $taskId): bool
    {
        return DB::table('task_user')
            ->where('board_id', $boardId)
            ->where('role', 'assignee')
            ->where('task_id', $taskId)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public static function assigneeIdsForBoardTask(int $boardId, int $taskId): array
    {
        return DB::table('task_user')
            ->where('board_id', $boardId)
            ->where('task_id', $taskId)
            ->where('role', 'assignee')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function userHasTaskInStatus(
        int $userId,
        int $boardId,
        int $taskId,
        string $status,
    ): bool {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $userId)
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.id', $taskId)
            ->where('tasks.status', $status)
            ->exists();
    }

    public static function taskExistsInStatus(
        int $boardId,
        int $taskId,
        string $status,
    ): bool {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.board_id', $boardId)
            ->where('task_user.role', 'assignee')
            ->where('tasks.id', $taskId)
            ->where('tasks.status', $status)
            ->exists();
    }
}
