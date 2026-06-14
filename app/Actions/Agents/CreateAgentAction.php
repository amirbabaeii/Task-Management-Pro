<?php

namespace App\Actions\Agents;

use App\Enums\AgentAutonomy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $manager, array $data): User
    {
        return DB::transaction(fn (): User => User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::password(32),
            'email_verified_at' => now(),
            'is_agent' => true,
            'agent_manager_id' => $manager->id,
            'agent_provider_connection_id' => $data['agent_provider_connection_id'] ?? null,
            'agent_model' => $data['agent_model'] ?? null,
            'agent_autonomy' => $data['agent_autonomy'] ?? AgentAutonomy::Approval->value,
            'agent_title' => $data['agent_title'] ?? null,
            'agent_profile' => $data['agent_profile'] ?? null,
            'agent_personality' => $data['agent_personality'] ?? null,
            'agent_skills' => $data['agent_skills'] ?? [],
        ]));
    }
}
