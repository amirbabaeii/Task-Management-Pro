<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email'],
        ]);
    }

    public function test_user_cannot_register_with_existing_email()
    {
        // Create an existing user
        User::factory()->create([
            'email' => 'test@example.com'
        ]);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => [ 'email' ]]]);
    }

    public function test_user_cannot_register_with_invalid_data()
    {
        $userData = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different'
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => [ 'name', 'email', 'password' ]]]);
    }

    public function test_user_cannot_register_with_invalid_email_formats()
    {
        $invalidEmails = [
            'not-an-email',
            'missing@domain',
            '@nodomain.com',
            'spaces@ domain.com',
            'double@@domain.com'
        ];

        foreach ($invalidEmails as $email) {
            $userData = [
                'name' => 'Test User',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ];

            $response = $this->postJson('/api/register', $userData);
            $response->assertStatus(422)
                ->assertJsonStructure(['data' => ['errors' => [ 'email' ]]]);
        }
    }

    public function test_user_cannot_register_with_invalid_name_formats()
    {
        $invalidNames = [
            '', // empty
            str_repeat('a', 256), // too long (max:255)
            ' ', // only spaces
        ];

        foreach ($invalidNames as $name) {
            $userData = [
                'name' => $name,
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123'
            ];

            $response = $this->postJson('/api/register', $userData);
            $response->assertStatus(422)
                ->assertJsonStructure(['data' => ['errors' => [ 'name' ]]]);
        }
    }

    public function test_user_cannot_register_with_invalid_password_formats()
    {
        $invalidPasswords = [
            'short', // too short (min:8)
            '12345678', // numeric only
            'abcdefgh', // lowercase only
            str_repeat('a', 256), // too long
            '', // empty
        ];

        foreach ($invalidPasswords as $password) {
            $userData = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $password,
                'password_confirmation' => $password
            ];

            $response = $this->postJson('/api/register', $userData);
            $response->assertStatus(422)
                ->assertJsonStructure(['data' => ['errors' => [ 'password' ]]]);
        }
    }

    public function test_user_cannot_register_with_missing_fields()
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => [ 'name', 'email', 'password' ]]]);
    }

    public function test_user_cannot_register_with_null_values()
    {
        $userData = [
            'name' => null,
            'email' => null,
            'password' => null,
            'password_confirmation' => null
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(422)
            ->assertJsonStructure(['data' => ['errors' => [ 'name', 'email', 'password' ]]]);
    }
} 