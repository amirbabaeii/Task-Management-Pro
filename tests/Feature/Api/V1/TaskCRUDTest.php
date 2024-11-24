<?php

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskCRUDTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user and token for all tests
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('TestToken')->plainTextToken;
    }

    public function test_can_list_tasks_as_authenticated_user()
    {
        // Arrange
        Task::factory()->count(15)->create();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson(route('api.v1.tasks.index'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'current_page',
                    'total',
                    'per_page',
                ],
                'message',
                'type'
            ])
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('message', 'List of tasks successfully received')
            ->assertJsonPath('type', 'success');
    }

    public function test_can_create_task_as_authenticated_user()
    {
        // Arrange
        $taskData = [
            'title' => 'New Test Task',
            'description' => 'Test Task Description',
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson(route('api.v1.tasks.store'), $taskData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'created_at',
                    'updated_at',
                ],
                'message',
                'type'
            ])
            ->assertJsonPath('message', 'Task successfully created')
            ->assertJsonPath('type', 'success');

        $this->assertDatabaseHas('tasks', [
            'title' => 'New Test Task',
            'description' => 'Test Task Description',
        ]);
    }

    public function test_cannot_create_task_with_invalid_data()
    {
        $invalidData = [
            'title' => '', // Empty title
            'status' => 'invalid_status', // Invalid status
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson(route('api.v1.tasks.store'), $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'data',
                'message',
                'data' => [
                    'errors'=> [
                        'title',
                        'status',
                    ]
                ],
                'type'
            ])
            ->assertJson([
                'type' => 'error'
            ]);

    }

    public function test_can_create_task_with_all_valid_fields()
    {
        $taskData = [
            'title' => 'New Test Task',
            'description' => 'Test Task Description',
            'status' => 'pending',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson(route('api.v1.tasks.store'), $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ],
                'message',
                'type'
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'New Test Task',
            'description' => 'Test Task Description',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_access_tasks_without_authentication()
    {
        // Act
        $response = $this->getJson(route('api.v1.tasks.index'));

        // Assert
        $response->assertStatus(401);
    }

    public function test_cannot_create_task_without_authentication()
    {
        // Arrange
        $taskData = [
            'title' => 'New Test Task',
            'description' => 'Test Task Description',
        ];

        // Act
        $response = $this->postJson(route('api.v1.tasks.store'), $taskData);

        // Assert
        $response->assertStatus(401);
    }

    public function test_can_update_task_as_authenticated_user()
    {
        // Arrange
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending',
            'progress' => 0
        ]);
        
        // Debug initial state
        \Log::info('Initial task:', $task->toArray());
        
        $task->users()->attach($this->user, ['role' => 'assignee']);
        
        $updateData = [
            'title' => 'Updated Task Title',
            'description' => 'Updated Task Description',
            'status' => 'in-progress',
            'progress' => 50,
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson(route('api.v1.tasks.update', $task->id), $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'progress',
                    'created_at',
                    'updated_at',
                ],
                'message',
                'type'
            ])
            ->assertJsonPath('message', 'Task successfully updated')
            ->assertJsonPath('type', 'success');

        // Add more specific assertions
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Task Title',
            'description' => 'Updated Task Description',
            'status' => 'in-progress',
            'progress' => 50,
        ]);

        // Add assertion to verify exact record
        $updatedTask = Task::find($task->id);
        $this->assertEquals(50, $updatedTask->progress);
    }

    public function test_cannot_update_task_with_invalid_data()
    {
        // Arrange
        $task = Task::factory()->create();
        $task->users()->attach($this->user, ['role' => 'assignee']);
        
        $invalidData = [
            'status' => 'invalid-status',
            'progress' => 150, // Invalid progress value
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson(route('api.v1.tasks.update', $task->id), $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'data' => [
                    'errors' => [
                        'status',
                        'progress'
                    ]
                ],
                'message',
                'type'
            ])
            ->assertJson([
                'type' => 'error'
            ]);
    }

    public function test_cannot_update_task_without_authentication()
    {
        // Arrange
        $task = Task::factory()->create();
        
        $updateData = [
            'title' => 'Updated Task Title',
        ];

        // Act
        $response = $this->putJson(route('api.v1.tasks.update', $task->id), $updateData);

        // Assert
        $response->assertStatus(401);
    }

    public function test_cannot_update_task_if_not_assigned()
    {
        // Arrange
        $task = Task::factory()->create();
        // Not attaching the user to the task
        
        $updateData = [
            'title' => 'Updated Task Title',
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson(route('api.v1.tasks.update', $task->id), $updateData);

        // Assert
        $response->assertStatus(403);
    }

    public function test_can_partially_update_task()
    {
        // Arrange
        $task = Task::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'status' => 'pending',
        ]);
        $task->users()->attach($this->user, ['role' => 'assignee']);

        $partialUpdate = [
            'status' => 'in-progress',
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson(route('api.v1.tasks.update', $task->id), $partialUpdate);

        // Assert
        $response->assertStatus(200);
        
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Original Title', // Should remain unchanged
            'description' => 'Original Description', // Should remain unchanged
            'status' => 'in-progress', // Should be updated
        ]);
    }
}

