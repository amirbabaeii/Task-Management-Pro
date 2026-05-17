<?php

namespace App\Support\Presenters;

use App\Models\User;

class AgentPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $agent): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'title' => $agent->agent_title,
            'profile' => $agent->agent_profile,
            'personality' => $agent->agent_personality,
            'skills' => $agent->agent_skills ?? [],
            'archived_at' => $agent->agent_archived_at,
            'created_at' => $agent->created_at,
        ];
    }
}
