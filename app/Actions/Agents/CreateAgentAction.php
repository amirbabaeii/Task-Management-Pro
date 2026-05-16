<?php

namespace App\Actions\Agents;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAgentAction
{
    /**
     * @param  array{name: string, email: string, agent_title?: string|null, agent_profile?: string|null, agent_personality?: string|null, agent_skills?: list<string>}  $data
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
            'agent_title' => $data['agent_title'] ?? null,
            'agent_profile' => $data['agent_profile'] ?? null,
            'agent_personality' => $data['agent_personality'] ?? null,
            'agent_skills' => $data['agent_skills'] ?? [],
        ]));
    }
}
