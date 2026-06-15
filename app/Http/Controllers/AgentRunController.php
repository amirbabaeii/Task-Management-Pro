<?php

namespace App\Http\Controllers;

use App\Actions\AgentRuns\CreateAgentRunAction;
use App\Http\Requests\AgentRuns\StoreAgentRunRequest;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\BoardTaskAssignments;
use App\Support\Presenters\AgentRunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRunController extends Controller
{
    public function __construct(
        private readonly CreateAgentRunAction $createAgentRun,
    ) {}

    public function index(Request $request, Board $board, Task $task): JsonResponse
    {
        $this->resolveBoardTask($board, $task);

        $runs = $request->user()
            ->managedAgentRuns()
            ->where('board_id', $board->id)
            ->where('task_id', $task->id)
            ->with(['agent:id,name', 'manager:id,name', 'actions'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($run): array => AgentRunPresenter::toArray($run))
            ->values()
            ->all();

        return response()->json(['agent_runs' => $runs]);
    }

    public function store(StoreAgentRunRequest $request, Board $board, Task $task): JsonResponse
    {
        $this->resolveBoardTask($board, $task);

        $agent = User::query()
            ->with('agentProviderConnection')
            ->findOrFail((int) $request->validated('agent_id'));

        $run = $this->createAgentRun->execute(
            $request->user(),
            $board,
            $task,
            $agent,
            $request->autonomy(),
        );

        $run->load(['agent:id,name', 'manager:id,name', 'actions']);

        return response()->json([
            'agent_run' => AgentRunPresenter::toArray($run),
        ], 201);
    }

    private function resolveBoardTask(Board $board, Task $task): void
    {
        $this->authorize('view', $board);

        abort_unless(
            BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id),
            404,
        );
    }
}
