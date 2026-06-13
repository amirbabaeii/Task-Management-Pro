<?php

namespace App\Actions\Notifications;

use App\Models\User;

class DeleteNotificationAction
{
    public function execute(User $user, string $notificationId): void
    {
        $user->notifications()
            ->findOrFail($notificationId)
            ->delete();
    }
}
