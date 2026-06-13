<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BoardMemberAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_dismiss_their_notification(): void
    {
        $invitedBy = User::factory()->create();
        $user = User::factory()->create();
        $board = $invitedBy->boards()->firstOrFail();

        $user->notify(new BoardMemberAddedNotification($board, $invitedBy));
        $notification = $user->notifications()->firstOrFail();

        $response = $this->actingAs($user)
            ->deleteJson(route('notifications.destroy', ['id' => $notification->id]));

        $response
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_user_cannot_dismiss_another_users_notification(): void
    {
        $invitedBy = User::factory()->create();
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();
        $board = $invitedBy->boards()->firstOrFail();

        $recipient->notify(new BoardMemberAddedNotification($board, $invitedBy));
        $notification = $recipient->notifications()->firstOrFail();

        $this->actingAs($otherUser)
            ->deleteJson(route('notifications.destroy', ['id' => $notification->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
    }
}
