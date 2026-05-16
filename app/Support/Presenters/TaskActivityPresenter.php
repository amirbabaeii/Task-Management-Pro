<?php

namespace App\Support\Presenters;

use App\Enums\TaskActivityKind;
use App\Models\TaskActivity;

class TaskActivityPresenter
{
    /**
     * @return array{id: int, kind: string, actor: array{id: int|null, name: string}, created_at: mixed, text: string, payload: array<string, mixed>}
     */
    public static function toArray(TaskActivity $activity): array
    {
        $actor = $activity->actor;

        return [
            'id' => $activity->id,
            'kind' => $activity->kind->value,
            'actor' => [
                'id' => $actor?->id,
                'name' => $actor?->name ?? 'System',
            ],
            'created_at' => $activity->created_at,
            'text' => self::describe($activity, $actor?->name ?? 'Someone'),
            'payload' => $activity->payload ?? [],
        ];
    }

    private static function describe(TaskActivity $activity, string $actorName): string
    {
        $payload = $activity->payload ?? [];

        return match ($activity->kind) {
            TaskActivityKind::Created => "{$actorName} created this task",
            TaskActivityKind::StatusChanged => sprintf(
                '%s moved this task from %s to %s',
                $actorName,
                $payload['from'] ?? 'unknown',
                $payload['to'] ?? 'unknown',
            ),
            TaskActivityKind::AssigneesChanged => self::describeAssigneeChange($actorName, $payload),
            TaskActivityKind::CommentAdded => "{$actorName} commented",
            TaskActivityKind::Archived => "{$actorName} archived this task",
            TaskActivityKind::Restored => "{$actorName} restored this task",
            TaskActivityKind::ChecklistItemAdded => sprintf(
                '%s added "%s" to the checklist',
                $actorName,
                $payload['title'] ?? 'an item',
            ),
            TaskActivityKind::ChecklistItemCompleted => sprintf(
                '%s completed "%s"',
                $actorName,
                $payload['title'] ?? 'a checklist item',
            ),
            TaskActivityKind::ChecklistItemReopened => sprintf(
                '%s reopened "%s"',
                $actorName,
                $payload['title'] ?? 'a checklist item',
            ),
            TaskActivityKind::ChecklistItemRenamed => sprintf(
                '%s renamed "%s" to "%s"',
                $actorName,
                $payload['from'] ?? 'a checklist item',
                $payload['to'] ?? 'a checklist item',
            ),
            TaskActivityKind::ChecklistItemDeleted => sprintf(
                '%s deleted "%s" from the checklist',
                $actorName,
                $payload['title'] ?? 'an item',
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function describeAssigneeChange(string $actorName, array $payload): string
    {
        $added = self::names($payload['added'] ?? []);
        $removed = self::names($payload['removed'] ?? []);

        if ($added !== '' && $removed !== '') {
            return "{$actorName} added {$added} and removed {$removed}";
        }
        if ($added !== '') {
            return "{$actorName} added {$added}";
        }
        if ($removed !== '') {
            return "{$actorName} removed {$removed}";
        }

        return "{$actorName} updated assignees";
    }

    /**
     * @param  array<int, array{id?: int, name?: string}>  $list
     */
    private static function names(array $list): string
    {
        $names = array_values(array_filter(array_map(
            fn (array $item): string => (string) ($item['name'] ?? ''),
            $list,
        )));

        if ($names === []) {
            return '';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) === 2) {
            return $names[0].' and '.$names[1];
        }

        $last = array_pop($names);

        return implode(', ', $names).', and '.$last;
    }
}
