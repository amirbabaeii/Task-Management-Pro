<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDeadlineReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Task $task,
        public readonly Board $board,
        public readonly string $deadlineState,
        public readonly CarbonInterface $reminderDate,
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
            'kind' => 'task_deadline_reminder',
            'deadline_state' => $this->deadlineState,
            'reminder_date' => $this->reminderDate->toDateString(),
            'task' => [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'deadline_at' => $this->task->deadline_at?->toDateTimeString(),
            ],
            'board' => [
                'id' => $this->board->id,
                'name' => $this->board->name,
            ],
        ];
    }
}
