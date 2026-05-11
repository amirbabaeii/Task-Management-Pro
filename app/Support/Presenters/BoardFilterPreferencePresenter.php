<?php

namespace App\Support\Presenters;

use App\Enums\TaskPriority;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BoardFilterPreferencePresenter
{
    /**
     * @return array{search: string, priorities: list<string>, assignee_id: int|null, deadline: string, view: string}
     */
    public static function defaults(): array
    {
        return [
            'search' => '',
            'priorities' => [],
            'assignee_id' => null,
            'deadline' => 'all',
            'view' => 'active',
        ];
    }

    /**
     * @return array{search: string, priorities: list<string>, assignee_id: int|null, deadline: string, view: string}
     */
    public static function forUser(Board $board, User $user): array
    {
        $value = DB::table('board_members')
            ->where('board_id', $board->id)
            ->where('user_id', $user->id)
            ->value('filter_preferences');

        return self::normalize($value);
    }

    /**
     * @return array{search: string, priorities: list<string>, assignee_id: int|null, deadline: string, view: string}
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return self::defaults();
        }

        $defaults = self::defaults();
        $priorities = array_values(array_filter(
            array_map('strval', is_array($value['priorities'] ?? null) ? $value['priorities'] : []),
            fn (string $priority): bool => in_array($priority, TaskPriority::values(), true),
        ));

        return [
            'search' => is_string($value['search'] ?? null)
                ? mb_substr(trim($value['search']), 0, 150)
                : $defaults['search'],
            'priorities' => array_values(array_unique($priorities)),
            'assignee_id' => is_numeric($value['assignee_id'] ?? null)
                ? (int) $value['assignee_id']
                : null,
            'deadline' => in_array($value['deadline'] ?? null, ['all', 'overdue', 'today', 'upcoming', 'none'], true)
                ? $value['deadline']
                : $defaults['deadline'],
            'view' => in_array($value['view'] ?? null, ['active', 'archived'], true)
                ? $value['view']
                : $defaults['view'],
        ];
    }
}
