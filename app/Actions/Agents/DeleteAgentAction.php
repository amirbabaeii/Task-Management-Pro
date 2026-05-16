<?php

namespace App\Actions\Agents;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAgentAction
{
    public function execute(User $agent): void
    {
        DB::transaction(fn () => $agent->delete());
    }
}
