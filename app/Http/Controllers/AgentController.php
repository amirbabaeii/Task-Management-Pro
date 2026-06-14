<?php

namespace App\Http\Controllers;

use App\Actions\Agents\ArchiveAgentAction;
use App\Actions\Agents\CreateAgentAction;
use App\Actions\Agents\DeleteAgentAction;
use App\Actions\Agents\RestoreAgentAction;
use App\Actions\Agents\UpdateAgentAction;
use App\Http\Requests\Agents\StoreAgentRequest;
use App\Http\Requests\Agents\UpdateAgentRequest;
use App\Models\Board;
use App\Models\User;
use App\Support\Presenters\AgentPresenter;
use App\Support\Presenters\AiProviderConnectionPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(
        private readonly CreateAgentAction $createAgent,
        private readonly UpdateAgentAction $updateAgent,
        private readonly ArchiveAgentAction $archiveAgent,
        private readonly RestoreAgentAction $restoreAgent,
        private readonly DeleteAgentAction $deleteAgent,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Agents/Index', [
            'agents' => $this->agentPayloads(User::query()
                ->agentsManagedBy($request->user(), false)),
            'archivedAgents' => $this->agentPayloads(User::query()
                ->agentsManagedBy($request->user(), true)),
            'providerConnections' => $request->user()
                ->aiProviderConnections()
                ->orderBy('provider')
                ->get()
                ->map(fn ($connection): array => AiProviderConnectionPresenter::toArray($connection))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreAgentRequest $request): JsonResponse
    {
        $agent = $this->createAgent->execute($request->user(), $request->validated());

        return response()->json([
            'agent' => AgentPresenter::toArray($agent),
        ], 201);
    }

    public function update(UpdateAgentRequest $request, User $agent): JsonResponse
    {
        $agent = $this->resolveManagedAgent($request, $agent);
        $agent = $this->updateAgent->execute($agent, $request->validated());

        return response()->json([
            'agent' => AgentPresenter::toArray($agent),
        ]);
    }

    public function archive(Request $request, User $agent): JsonResponse
    {
        $agent = $this->resolveManagedAgent($request, $agent);
        $agent = $this->archiveAgent->execute($agent);

        return response()->json([
            'agent' => AgentPresenter::toArray($agent),
        ]);
    }

    public function restore(Request $request, User $agent): JsonResponse
    {
        $agent = $this->resolveManagedAgent($request, $agent);
        $agent = $this->restoreAgent->execute($agent);

        return response()->json([
            'agent' => AgentPresenter::toArray($agent),
        ]);
    }

    public function destroy(Request $request, User $agent): JsonResponse
    {
        $agent = $this->resolveManagedAgent($request, $agent);
        $id = $agent->id;

        $this->deleteAgent->execute($agent);

        return response()->json(['id' => $id]);
    }

    private function resolveManagedAgent(Request $request, User $agent): User
    {
        abort_unless(
            $agent->is_agent && (int) $agent->agent_manager_id === (int) $request->user()->id,
            404,
        );

        return $agent;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function agentPayloads(Builder $query): array
    {
        $agents = $query
            ->withAgentWorkload()
            ->orderBy('name')
            ->get();

        $boardIds = $agents
            ->flatMap(fn (User $agent) => $agent->assignedTasks
                ->map(fn ($task) => $task->pivot?->board_id))
            ->filter()
            ->unique()
            ->values();

        $boardNames = Board::query()
            ->whereIn('id', $boardIds)
            ->pluck('name', 'id')
            ->all();

        return $agents
            ->map(fn (User $agent): array => AgentPresenter::toArray($agent, $boardNames))
            ->values()
            ->all();
    }
}
