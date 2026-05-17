<?php

namespace App\Actions\Agents;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreAgentAction
{
    public function execute(User $agent): User
    {
        return DB::transaction(function () use ($agent): User {
            if ($agent->agent_archived_at !== null) {
                $agent->forceFill([
                    'agent_archived_at' => null,
                ])->save();
            }

            return $agent;
        });
    }
}
