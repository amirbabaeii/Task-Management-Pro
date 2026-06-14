<?php

namespace App\Actions\Agents;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAgentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $agent, array $data): User
    {
        return DB::transaction(function () use ($agent, $data): User {
            $agent->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'agent_provider_connection_id' => $data['agent_provider_connection_id'] ?? null,
                'agent_model' => $data['agent_model'] ?? null,
                'agent_autonomy' => $data['agent_autonomy'],
                'agent_title' => $data['agent_title'] ?? null,
                'agent_profile' => $data['agent_profile'] ?? null,
                'agent_personality' => $data['agent_personality'] ?? null,
                'agent_skills' => $data['agent_skills'] ?? [],
            ]);
            $agent->save();

            return $agent;
        });
    }
}
