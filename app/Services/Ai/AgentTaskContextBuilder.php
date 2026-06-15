<?php

namespace App\Services\Ai;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use BackedEnum;

class AgentTaskContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Board $board, Task $task, User $agent): array
    {
        return [
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'description' => $board->description,
                'columns' => BoardColumn::orderedForBoard($board)
                    ->map(fn (BoardColumn $column): array => [
                        'status' => $column->status,
                        'label' => $column->label,
                    ])
                    ->values()
                    ->all(),
            ],
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority instanceof BackedEnum
                    ? $task->priority->value
                    : $task->priority,
                'progress' => $task->progress,
                'tags' => $task->tags ?? [],
                'deadline_at' => $task->deadline_at?->toIso8601String(),
                'assignees' => $this->assignees($board, $task),
                'checklist' => $this->checklist($task),
                'comments' => $this->comments($task),
                'recent_activity' => $this->activities($task),
            ],
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'title' => $agent->agent_title,
                'profile' => $agent->agent_profile,
                'personality' => $agent->agent_personality,
                'skills' => $agent->agent_skills ?? [],
            ],
        ];
    }

    /**
     * @return list<array{id: int, name: string, is_agent: bool}>
     */
    private function assignees(Board $board, Task $task): array
    {
        return $task->assignees()
            ->wherePivot('board_id', $board->id)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.is_agent'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'is_agent' => $user->is_agent,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, completed: bool}>
     */
    private function checklist(Task $task): array
    {
        return $task->checklistItems()
            ->get()
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'completed' => $item->completed_at !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function comments(Task $task): array
    {
        return TaskComment::query()
            ->with('user:id,name')
            ->where('task_id', $task->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('ai.context.comment_limit'))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (TaskComment $comment): array => [
                'id' => $comment->id,
                'parent_id' => $comment->parent_id,
                'author' => $comment->user?->name ?? 'Unknown user',
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activities(Task $task): array
    {
        return TaskActivity::query()
            ->with('actor:id,name')
            ->where('task_id', $task->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('ai.context.activity_limit'))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (TaskActivity $activity): array => [
                'id' => $activity->id,
                'kind' => $activity->kind->value,
                'actor' => $activity->actor?->name ?? 'System',
                'payload' => $activity->payload ?? [],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
