<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly TaskComment $reply,
        public readonly TaskComment $parent,
        public readonly User $author,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'comment_reply',
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ],
            'reply' => [
                'id' => $this->reply->id,
                'snippet' => mb_substr($this->reply->content, 0, 120),
            ],
            'parent_comment_id' => $this->parent->id,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ],
        ];
    }
}
