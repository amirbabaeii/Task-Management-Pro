<?php

namespace App\Support\Presenters;

use App\Models\User;

class AgentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $agent): array
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
            'created_at' => $agent->created_at,
        ];
    }

    private static function countOr(User $agent, string $attribute, callable $fallback): int
    {
        $value = $agent->getAttribute($attribute);

        return $value === null ? (int) $fallback() : (int) $value;
    }
}
