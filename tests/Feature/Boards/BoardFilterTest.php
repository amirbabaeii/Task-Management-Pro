<?php

namespace Tests\Feature\Boards;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoardFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_member_can_save_their_filter_preferences(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $board = $this->boardFor($owner);
        $board->members()->attach($collaborator->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($collaborator)->patchJson(
            route('boards.filters.update', ['board' => $board]),
            [
                'search' => ' api cleanup ',
                'priorities' => ['high', 'low', 'high'],
                'assignee_id' => $collaborator->id,
                'deadline' => 'upcoming',
                'view' => 'archived',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('filters.search', 'api cleanup')
            ->assertJsonPath('filters.priorities', ['high', 'low'])
            ->assertJsonPath('filters.assignee_id', $collaborator->id)
            ->assertJsonPath('filters.deadline', 'upcoming')
            ->assertJsonPath('filters.view', 'archived');

        $this->assertEquals(
            [
                'search' => 'api cleanup',
                'priorities' => ['high', 'low'],
                'assignee_id' => $collaborator->id,
                'deadline' => 'upcoming',
                'view' => 'archived',
            ],
            $this->storedFilters($board, $collaborator),
        );

        $this->assertNull($this->storedFilters($board, $owner));
    }

    public function test_filter_preferences_validate_board_membership_values(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);

        $response = $this->actingAs($owner)->patchJson(
            route('boards.filters.update', ['board' => $board]),
            [
                'search' => str_repeat('a', 151),
                'priorities' => ['urgent'],
                'assignee_id' => $stranger->id,
                'deadline' => 'later',
                'view' => 'deleted',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'search',
                'priorities.0',
                'assignee_id',
                'deadline',
                'view',
            ]);
    }

    public function test_non_member_cannot_save_board_filter_preferences(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $board = $this->boardFor($owner);

        $this->actingAs($stranger)
            ->patchJson(route('boards.filters.update', ['board' => $board]), [
                'search' => 'anything',
                'priorities' => [],
                'deadline' => 'all',
                'view' => 'active',
            ])
            ->assertNotFound();
    }

    private function boardFor(User $user): Board
    {
        return app(EnsureUserHasDefaultBoardAction::class)->execute($user);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedFilters(Board $board, User $user): ?array
    {
        $value = DB::table('board_members')
            ->where('board_id', $board->id)
            ->where('user_id', $user->id)
            ->value('filter_preferences');

        return $value === null ? null : json_decode((string) $value, true);
    }
}
