<?php

namespace App\Actions\TaskComments;

use App\Actions\Tasks\RecordTaskActivityAction;
use App\Enums\TaskActivityKind;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class AddTaskCommentAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array{content: string, parent_id?: int|null}  $data
     */
    public function execute(User $author, Task $task, array $data): TaskComment
    {
        $comment = $task->comments()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => $author->id,
            'content' => $data['content'],
        ]);

        $comment->load('user:id,name', 'replies.user:id,name');

        $this->recordActivity->execute(
            $task,
            TaskActivityKind::CommentAdded,
            [
                'comment_id' => $comment->id,
                'snippet' => mb_substr($comment->content, 0, 80),
            ],
            $author,
        );

        return $comment;
    }
}
