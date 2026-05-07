<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly Board $board,
        public readonly User $assignedBy,
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
            'kind' => 'task_assigned',
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
            ],
            'board' => [
                'id' => $this->board->id,
                'name' => $this->board->name,
            ],
            'assigned_by' => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ],
        ];
    }
}
