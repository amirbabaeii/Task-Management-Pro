<?php

namespace App\Actions\AgentRuns;

use App\Enums\AgentAutonomy;
use App\Enums\AgentRunStatus;
use App\Models\AgentRun;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AgentTaskContextBuilder;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAgentRunAction
{
    public function __construct(
        private readonly AgentTaskContextBuilder $contextBuilder,
    ) {}

    public function execute(
        User $manager,
        Board $board,
        Task $task,
        User $agent,
        ?AgentAutonomy $requestedAutonomy = null,
    ): AgentRun {
        if (! config('ai.execution.enabled')) {
            throw ValidationException::withMessages([
                'agent_id' => 'Agent execution is currently disabled.',
            ]);
        }

        $this->validateAgent($manager, $board, $task, $agent);

        $autonomy = $requestedAutonomy ?? $agent->agent_autonomy;

        if (! $agent->agent_autonomy->allows($autonomy)) {
            throw ValidationException::withMessages([
                'autonomy' => 'The requested autonomy is higher than this agent allows.',
            ]);
        }

        $connection = $agent->agentProviderConnection;

        if ($connection === null) {
            throw ValidationException::withMessages([
                'agent_id' => 'Choose an agent with a provider connection.',
            ]);
        }

        if ((int) $connection->user_id !== (int) $manager->id) {
            throw ValidationException::withMessages([
                'agent_id' => 'Choose an agent with a provider connection owned by this manager.',
            ]);
        }

        if ($connection->verified_at === null) {
            throw ValidationException::withMessages([
                'agent_id' => 'Verify this agent provider connection before starting a run.',
            ]);
        }

        return DB::transaction(function () use ($manager, $board, $task, $agent, $connection, $autonomy): AgentRun {
            Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeRunExists = AgentRun::query()
                ->where('task_id', $task->id)
                ->whereIn('status', AgentRunStatus::activeValues())
                ->lockForUpdate()
                ->exists();

            if ($activeRunExists) {
                throw ValidationException::withMessages([
                    'task' => 'This task already has an active agent run.',
                ]);
            }

            return AgentRun::query()->create([
                'board_id' => $board->id,
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'manager_id' => $manager->id,
                'provider_connection_id' => $connection->id,
                'provider' => $connection->provider,
                'model' => $agent->agent_model ?? $connection->default_model,
                'autonomy' => $autonomy,
                'status' => AgentRunStatus::Queued,
                'context_snapshot' => $this->contextBuilder->build($board, $task, $agent),
            ]);
        });
    }

    private function validateAgent(User $manager, Board $board, Task $task, User $agent): void
    {
        abort_unless($agent->is_agent, 404);
        abort_unless((int) $agent->agent_manager_id === (int) $manager->id, 404);
        abort_unless($agent->agent_archived_at === null, 404);

        abort_unless($board->hasMember($agent), 422, 'The agent must be a board member.');

        if (! BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id)) {
            abort(404);
        }

        if (! BoardTaskAssignments::userHasTaskOnBoard($agent->id, $board->id, $task->id)) {
            throw ValidationException::withMessages([
                'agent_id' => 'The agent must be assigned to this task.',
            ]);
        }
    }
}
