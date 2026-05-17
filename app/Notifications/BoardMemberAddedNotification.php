<?php

namespace App\Notifications;

use App\Models\Board;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BoardMemberAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Board $board,
        public readonly User $invitedBy,
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
            'kind' => 'board_member_added',
            'board' => [
                'id' => $this->board->id,
                'name' => $this->board->name,
            ],
            'invited_by' => [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
            ],
        ];
    }
}
