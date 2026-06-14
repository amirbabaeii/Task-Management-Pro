<?php

namespace Tests\Feature;

use App\Enums\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_ai_settings(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->get(route('ai-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Ai')
                ->where('connection.configured', false)
                ->where('connection.default_model', 'gpt-5.5'));
    }

    public function test_manager_can_save_and_rotate_openai_connection(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->putJson(route('ai-settings.openai.update'), [
                'api_key' => 'sk-first-secret',
                'default_model' => 'gpt-5.5',
            ])
            ->assertOk()
            ->assertJsonPath('connection.configured', true)
            ->assertJsonMissing(['api_key' => 'sk-first-secret']);

        $connection = $manager->openAiConnection()->firstOrFail();
        $connection->forceFill(['verified_at' => now()])->save();

        $this->actingAs($manager)
            ->putJson(route('ai-settings.openai.update'), [
                'api_key' => 'sk-rotated-secret',
                'default_model' => 'gpt-5.5-custom',
            ])
            ->assertOk()
            ->assertJsonPath(
                'connection.default_model',
                'gpt-5.5-custom',
            );

        $connection->refresh();

        $this->assertSame('sk-rotated-secret', $connection->api_key);
        $this->assertNull($connection->verified_at);
        $this->assertSame('gpt-5.5-custom', $connection->default_model);
    }

    public function test_existing_key_is_preserved_when_only_model_changes(): void
    {
        $manager = User::factory()->create();
        $connection = $manager->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-existing-secret',
            'default_model' => 'gpt-5.5',
            'verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->putJson(route('ai-settings.openai.update'), [
                'api_key' => '',
                'default_model' => 'gpt-5.5-new',
            ])
            ->assertOk();

        $connection->refresh();

        $this->assertSame('sk-existing-secret', $connection->api_key);
        $this->assertNotNull($connection->verified_at);
        $this->assertSame('gpt-5.5-new', $connection->default_model);
    }

    public function test_manager_can_delete_their_openai_connection(): void
    {
        $manager = User::factory()->create();
        $manager->aiProviderConnections()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-secret',
            'default_model' => 'gpt-5.5',
        ]);

        $this->actingAs($manager)
            ->deleteJson(route('ai-settings.openai.destroy'))
            ->assertOk()
            ->assertJsonPath('connection.configured', false);

        $this->assertDatabaseCount('ai_provider_connections', 0);
    }

    public function test_managed_agent_cannot_access_ai_settings(): void
    {
        $manager = User::factory()->create();
        $agent = User::factory()->create([
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
        ]);

        $this->actingAs($agent)
            ->get(route('ai-settings.edit'))
            ->assertNotFound();
        $this->actingAs($agent)
            ->putJson(route('ai-settings.openai.update'), [
                'api_key' => 'sk-agent-secret',
                'default_model' => 'gpt-5.5',
            ])
            ->assertForbidden();
    }
}
