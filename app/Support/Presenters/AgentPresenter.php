<?php

namespace App\Support\Presenters;

use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Collection;

class AgentPresenter
{
    /**
     * @param  array<int|string, string>  $boardNamesById
     * @return array<string, mixed>
     */
    public static function toArray(User $agent, array $boardNamesById = []): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'title' => $agent->agent_title,
            'profile' => $agent->agent_profile,
            'personality' => $agent->agent_personality,
            'skills' => $agent->agent_skills ?? [],
            'archived_at' => $agent->agent_archived_at,
            'workload' => [
                'boards' => self::countOr($agent, 'boards_count', fn (): int => $agent->accessibleBoards()->count()),
                'active_tasks' => self::countOr($agent, 'active_tasks_count', fn (): int => $agent->assignedTasks()
                    ->whereNull('tasks.archived_at')
                    ->count()),
                'overdue_tasks' => self::countOr($agent, 'overdue_tasks_count', fn (): int => $agent->assignedTasks()
                    ->whereNull('tasks.archived_at')
                    ->whereNotNull('tasks.deadline_at')
                    ->where('tasks.deadline_at', '<', now())
                    ->count()),
            ],
            'next_tasks' => self::nextTasks($agent, $boardNamesById),
            'created_at' => $agent->created_at,
        ];
    }

    private static function countOr(User $agent, string $attribute, callable $fallback): int
    {
        $value = $agent->getAttribute($attribute);

        return $value === null ? (int) $fallback() : (int) $value;
    }

    /**
     * @param  array<int|string, string>  $boardNamesById
     * @return list<array<string, mixed>>
     */
    private static function nextTasks(User $agent, array $boardNamesById): array
    {
        return self::nextTaskModels($agent)
            ->take(3)
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority instanceof BackedEnum
                    ? $task->priority->value
                    : $task->priority,
                'deadline_at' => $task->deadline_at,
                'board_id' => $task->pivot?->board_id,
                'board_name' => $boardNamesById[$task->pivot?->board_id] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Task>
     */
    private static function nextTaskModels(User $agent): Collection
    {
        if ($agent->relationLoaded('assignedTasks')) {
            return $agent->assignedTasks;
        }

        return $agent->assignedTasks()
            ->whereNull('tasks.archived_at')
            ->select([
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.priority',
                'tasks.deadline_at',
            ])
            ->orderByRaw('tasks.deadline_at is null')
            ->orderBy('tasks.deadline_at')
            ->orderBy('tasks.id')
            ->limit(3)
            ->get();
    }
}
