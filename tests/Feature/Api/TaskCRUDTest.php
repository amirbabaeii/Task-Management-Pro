<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaskCRUDTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_tasks_route_as_authenticated_user()
    {
        // Create a user and get a Sanctum token
        $user = User::factory()->create();
        $token = $user->createToken('TestToken')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson(route('api.v1.tasks.index'));
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'message',
                'data' => [
                    'current_page',
                    'data'
                ]
            ]);
    }
}

