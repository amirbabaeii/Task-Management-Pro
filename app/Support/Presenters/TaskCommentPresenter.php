<?php

namespace App\Support\Presenters;

use App\Models\TaskComment;

class TaskCommentPresenter
{
    /**
     * @return array{id: int, parent_id: int|null, content: string, created_at: mixed, user: array{id: int|null, name: string}, replies: list<array<string, mixed>>}
     */
    public static function toArray(TaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'created_at' => $comment->created_at,
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name ?? 'Unknown user',
            ],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies
                    ->map(fn (TaskComment $reply): array => self::toArray($reply))
                    ->values()
                    ->all()
                : [],
        ];
    }
}
