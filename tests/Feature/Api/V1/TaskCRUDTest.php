<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Boards\EnsureUserHasDefaultBoardAction;
use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Board $board;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->board = app(EnsureUserHasDefaultBoardAction::class)
            ->execute($this->user);
        $this->token = $this->user
            ->createToken('TestToken')
            ->plainTextToken;
    }

    public function test_member_can_list_only_tasks_on_the_requested_board(): void
    {
        $included = $this->taskOnBoard($this->board, [
            'title' => 'Included task',
        ]);
        $otherBoard = Board::factory()->create();
        $excluded = $this->taskOnBoard($otherBoard, [
            'title' => 'Excluded task',
        ]);

        $response = $this->api()
            ->getJson(route('api.v1.boards.tasks.index', $this->board));

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $included->id)
            ->assertJsonMissing(['id' => $excluded->id]);
    }

    public function test_member_can_create_a_board_task(): void
    {
        $response = $this->api()->postJson(
            route('api.v1.boards.tasks.store', $this->board),
            [
                'title' => 'New board task',
                'description' => 'Created through the board-scoped API.',
                'status' => 'pending',
                'priority' => 'high',
                'tags' => ['api', 'board'],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'New board task')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.assignees.0.id', $this->user->id);

        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('task_user', [
            'task_id' => $taskId,
            'user_id' => $this->user->id,
            'board_id' => $this->board->id,
            'role' => 'assignee',
        ]);
    }

    public function test_creation_validates_board_columns_and_members(): void
    {
        $stranger = User::factory()->create();

        $this->api()->postJson(
            route('api.v1.boards.tasks.store', $this->board),
            [
                'title' => '',
                'status' => 'blocked',
                'priority' => 'urgent',
                'assignee_ids' => [$stranger->id],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonStructure([
                'data' => [
                    'errors' => [
                        'title',
                        'status',
                        'priority',
                        'assignee_ids.0',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_collaborator_can_create_a_task_for_a_board_member(): void
    {
        $collaborator = User::factory()->create();
        $this->board->members()->attach($collaborator->id, [
            'role' => BoardRole::Collaborator->value,
            'joined_at' => now(),
        ]);
        $token = $collaborator
            ->createToken('CollaboratorToken')
            ->plainTextToken;

        $response = $this->withToken($token)->postJson(
            route('api.v1.boards.tasks.store', $this->board),
            [
                'title' => 'Collaborative task',
                'status' => 'pending',
                'priority' => 'medium',
                'assignee_ids' => [$this->user->id],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.assignees.0.id', $this->user->id);
    }

    public function test_member_can_partially_update_a_board_task(): void
    {
        $task = $this->taskOnBoard($this->board, [
            'title' => 'Original title',
            'status' => 'pending',
            'priority' => 'low',
        ]);

        $response = $this->api()->patchJson(
            route('api.v1.boards.tasks.update', [$this->board, $task]),
            [
                'title' => 'Updated title',
                'status' => 'in-progress',
                'progress' => 50,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.status', 'in-progress')
            ->assertJsonPath('data.progress', 50);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated title',
            'status' => 'in-progress',
            'priority' => 'low',
            'progress' => 50,
        ]);
    }

    public function test_task_from_another_board_cannot_be_updated(): void
    {
        $otherBoard = Board::factory()->create();
        $task = $this->taskOnBoard($otherBoard);

        $this->api()->patchJson(
            route('api.v1.boards.tasks.update', [$this->board, $task]),
            ['title' => 'Leaked update'],
        )->assertNotFound();

        $this->assertNotSame('Leaked update', $task->fresh()->title);
    }

    public function test_non_member_cannot_access_board_tasks(): void
    {
        $stranger = User::factory()->create();
        $token = $stranger->createToken('StrangerToken')->plainTextToken;

        $this->withToken($token)
            ->getJson(route('api.v1.boards.tasks.index', $this->board))
            ->assertNotFound();

        $this->withToken($token)
            ->postJson(route('api.v1.boards.tasks.store', $this->board), [
                'title' => 'Unauthorized task',
                'status' => 'pending',
                'priority' => 'medium',
            ])
            ->assertNotFound();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $task = $this->taskOnBoard($this->board);

        $this->getJson(route('api.v1.boards.tasks.index', $this->board))
            ->assertUnauthorized();
        $this->postJson(route('api.v1.boards.tasks.store', $this->board), [])
            ->assertUnauthorized();
        $this->patchJson(
            route('api.v1.boards.tasks.update', [$this->board, $task]),
            [],
        )->assertUnauthorized();
    }

    public function test_priority_filter_is_scoped_to_the_board(): void
    {
        $this->taskOnBoard($this->board, ['priority' => 'high']);
        $this->taskOnBoard($this->board, ['priority' => 'low']);
        $otherBoard = Board::factory()->create();
        $this->taskOnBoard($otherBoard, ['priority' => 'high']);

        $response = $this->api()->getJson(route(
            'api.v1.boards.tasks.index',
            ['board' => $this->board, 'priority' => 'high'],
        ));

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.priority', 'high');
    }

    private function api(): static
    {
        return $this->withToken($this->token);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function taskOnBoard(Board $board, array $attributes = []): Task
    {
        $task = Task::factory()->create($attributes);
        $task->users()->attach($board->user_id, [
            'board_id' => $board->id,
            'role' => 'assignee',
            'sort_order' => 1,
        ]);

        return $task;
    }
}
