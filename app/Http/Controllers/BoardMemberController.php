<?php

namespace App\Http\Controllers;

use App\Actions\Boards\AddBoardMemberAction;
use App\Actions\Boards\RemoveBoardMemberAction;
use App\Enums\BoardRole;
use App\Http\Requests\Boards\AddBoardMemberRequest;
use App\Models\Board;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BoardMemberController extends Controller
{
    public function __construct(
        private readonly AddBoardMemberAction $addMember,
        private readonly RemoveBoardMemberAction $removeMember,
    ) {}

    public function index(Request $request, Board $board): JsonResponse
    {
        $this->authorize('view', $board);

        return response()->json([
            'members' => $this->serialize($board),
            'available_agents' => $this->availableAgents($request->user(), $board),
        ]);
    }

    public function store(AddBoardMemberRequest $request, Board $board): JsonResponse
    {
        $this->authorize('manageMembers', $board);

        $invitee = $request->invitee();
        abort_if($invitee === null, Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->addMember->execute(
            $board,
            $invitee,
            BoardRole::Collaborator,
            $request->user(),
        );

        return response()->json([
            'members' => $this->serialize($board->fresh()),
            'available_agents' => $this->availableAgents($request->user(), $board),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Board $board, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $board);

        $this->removeMember->execute($board, $user);

        return response()->json([
            'members' => $this->serialize($board->fresh()),
            'available_agents' => $this->availableAgents($request->user(), $board),
        ]);
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string, joined_at: mixed, is_agent: bool, agent_title: string|null, agent_archived_at: mixed, is_archived_agent: bool}>
     */
    private function serialize(Board $board): array
    {
        return $board->members()
            ->orderByPivot('role')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
                'is_agent' => $member->is_agent,
                'agent_title' => $member->agent_title,
                'agent_archived_at' => $member->agent_archived_at,
                'is_archived_agent' => $member->is_agent && $member->agent_archived_at !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, email: string, agent_title: string|null}>
     */
    private function availableAgents(User $manager, Board $board): array
    {
        $memberIds = $board->members()
            ->select('users.id')
            ->pluck('users.id');

        return User::query()
            ->agentsManagedBy($manager, false)
            ->whereNotIn('id', $memberIds)
            ->orderBy('name')
            ->get()
            ->map(fn (User $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'agent_title' => $agent->agent_title,
            ])
            ->values()
            ->all();
    }
}
