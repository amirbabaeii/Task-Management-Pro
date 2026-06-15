<?php

namespace App\Notifications;

use App\Models\AgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentRunApprovalRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AgentRun $run,
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
        $this->run->loadMissing('board', 'task', 'agent');

        return [
            'kind' => 'agent_run_approval_required',
            'run' => [
                'id' => $this->run->id,
                'status' => $this->run->status->value,
                'summary' => $this->run->summary,
            ],
            'task' => [
                'id' => $this->run->task?->id,
                'title' => $this->run->task?->title,
            ],
            'board' => [
                'id' => $this->run->board?->id,
                'name' => $this->run->board?->name,
            ],
            'agent' => [
                'id' => $this->run->agent?->id,
                'name' => $this->run->agent?->name,
            ],
        ];
    }
}
