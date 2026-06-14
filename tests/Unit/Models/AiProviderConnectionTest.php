<?php

namespace Tests\Unit\Models;

use App\Enums\AiProvider;
use App\Models\AiProviderConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_and_hidden(): void
    {
        $user = User::factory()->create();
        $connection = AiProviderConnection::create([
            'user_id' => $user->id,
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-secret',
            'default_model' => AiProviderConnection::DEFAULT_MODEL,
        ]);

        $storedKey = AiProviderConnection::query()
            ->whereKey($connection)
            ->getQuery()
            ->value('api_key');

        $this->assertNotSame('sk-test-secret', $storedKey);
        $this->assertSame('sk-test-secret', $connection->fresh()->api_key);
        $this->assertArrayNotHasKey('api_key', $connection->toArray());
    }

    public function test_manager_has_one_openai_connection(): void
    {
        $user = User::factory()->create();
        $connection = $user->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-secret',
            'default_model' => AiProviderConnection::DEFAULT_MODEL,
        ]);

        $this->assertTrue($user->openAiConnection->is($connection));
        $this->assertSame(
            AiProviderConnection::DEFAULT_MODEL,
            $user->openAiConnection->default_model,
        );
    }
}
