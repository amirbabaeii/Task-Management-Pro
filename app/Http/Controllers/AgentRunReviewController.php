<?php

namespace App\Http\Controllers;

use App\Actions\AgentRuns\ApplyAgentRunAction;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunStatus;
use App\Jobs\Agents\ExecuteAgentRunJob;
use App\Models\AgentRun;
use App\Models\AgentRunAction;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use App\Support\Presenters\AgentRunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgentRunReviewController extends Controller
{
    public function __construct(
        private readonly ApplyAgentRunAction $applyAgentRunAction,
    ) {}

    public function approve(
        Request $request,
        AgentRun $agentRun,
        AgentRunAction $action,
    ): JsonResponse {
        $this->authorizeManager($request, $agentRun);
        $this->ensureActionBelongsToRun($agentRun, $action);

        if ($action->status === AgentRunActionStatus::Rejected) {
            throw ValidationException::withMessages([
                'action' => 'Rejected actions cannot be approved.',
            ]);
        }

        if ($action->status === AgentRunActionStatus::Applied) {
            return $this->runResponse($agentRun);
        }

        if ($action->status !== AgentRunActionStatus::Proposed) {
            throw ValidationException::withMessages([
                'action' => 'Only proposed actions can be approved.',
            ]);
        }

        $this->applyAgentRunAction->execute($action, $request->user());
        $this->finishRunIfReviewed($agentRun);

        return $this->runResponse($agentRun);
    }

    public function reject(
        Request $request,
        AgentRun $agentRun,
        AgentRunAction $action,
    ): JsonResponse {
        $this->authorizeManager($request, $agentRun);
        $this->ensureActionBelongsToRun($agentRun, $action);

        if ($action->status === AgentRunActionStatus::Applied) {
            throw ValidationException::withMessages([
                'action' => 'Applied actions cannot be rejected.',
            ]);
        }

        if ($action->status === AgentRunActionStatus::Rejected) {
            return $this->runResponse($agentRun);
        }

        if ($action->status !== AgentRunActionStatus::Proposed) {
            throw ValidationException::withMessages([
                'action' => 'Only proposed actions can be rejected.',
            ]);
        }

        $action->forceFill([
            'status' => AgentRunActionStatus::Rejected,
            'approved_by' => $request->user()->id,
            'rejected_at' => now(),
            'error_message' => null,
        ])->save();

        $this->finishRunIfReviewed($agentRun);

        return $this->runResponse($agentRun);
    }

    public function approveAll(Request $request, AgentRun $agentRun): JsonResponse
    {
        $this->authorizeManager($request, $agentRun);

        $agentRun->actions()
            ->where('status', AgentRunActionStatus::Proposed->value)
            ->get()
            ->each(fn (AgentRunAction $action) => $this->applyAgentRunAction->execute($action, $request->user()));

        $this->finishRunIfReviewed($agentRun);

        return $this->runResponse($agentRun);
    }

    public function retry(Request $request, AgentRun $agentRun): JsonResponse
    {
        $this->authorizeManager($request, $agentRun);

        $agentRun = DB::transaction(function () use ($agentRun): AgentRun {
            $agentRun = AgentRun::query()
                ->whereKey($agentRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($agentRun->status !== AgentRunStatus::Failed) {
                throw ValidationException::withMessages([
                    'run' => 'Only failed runs can be retried.',
                ]);
            }

            $this->validateRetryContext($agentRun);

            Task::query()
                ->whereKey($agentRun->task_id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeRunExists = AgentRun::query()
                ->where('task_id', $agentRun->task_id)
                ->where('id', '!=', $agentRun->id)
                ->whereIn('status', AgentRunStatus::activeValues())
                ->lockForUpdate()
                ->exists();

            if ($activeRunExists) {
                throw ValidationException::withMessages([
                    'task' => 'This task already has an active agent run.',
                ]);
            }

            $agentRun->actions()->delete();

            $agentRun->forceFill([
                'status' => AgentRunStatus::Queued,
                'error_code' => null,
                'error_message' => null,
                'summary' => null,
                'rationale' => null,
                'provider_response_id' => null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'started_at' => null,
                'completed_at' => null,
                'failed_at' => null,
            ])->save();

            return $agentRun;
        });

        ExecuteAgentRunJob::dispatch($agentRun);

        return $this->runResponse($agentRun);
    }

    private function validateRetryContext(AgentRun $agentRun): void
    {
        $agentRun->loadMissing('agent', 'board');
        $providerConnection = $agentRun->providerConnection()
            ->lockForUpdate()
            ->first();

        if (
            ! $agentRun->agent
            || ! $agentRun->board
            || ! $agentRun->agent->is_agent
            || (int) $agentRun->agent->agent_manager_id !== (int) $agentRun->manager_id
            || $agentRun->agent->agent_archived_at !== null
        ) {
            throw ValidationException::withMessages([
                'agent' => 'Choose an active agent managed by this user.',
            ]);
        }

        if (! $agentRun->board->hasMember($agentRun->agent)) {
            throw ValidationException::withMessages([
                'agent' => 'The agent must be a board member.',
            ]);
        }

        if (! BoardTaskAssignments::userHasTaskOnBoard(
            $agentRun->agent_id,
            $agentRun->board_id,
            $agentRun->task_id,
        )) {
            throw ValidationException::withMessages([
                'agent' => 'The agent must be assigned to this task.',
            ]);
        }

        if (
            ! $providerConnection
            || (int) $providerConnection->user_id !== (int) $agentRun->manager_id
        ) {
            throw ValidationException::withMessages([
                'agent' => 'Choose an agent with a provider connection owned by this manager.',
            ]);
        }

        if ($providerConnection->verified_at === null) {
            throw ValidationException::withMessages([
                'agent' => 'Verify this agent provider connection before retrying.',
            ]);
        }
    }

    private function authorizeManager(Request $request, AgentRun $agentRun): void
    {
        abort_unless((int) $agentRun->manager_id === (int) $request->user()->id, 404);
    }

    private function ensureActionBelongsToRun(AgentRun $agentRun, AgentRunAction $action): void
    {
        abort_unless((int) $action->agent_run_id === (int) $agentRun->id, 404);
    }

    private function finishRunIfReviewed(AgentRun $agentRun): void
    {
        $agentRun->refresh();

        if ($agentRun->status !== AgentRunStatus::AwaitingApproval) {
            return;
        }

        $hasPendingApproval = $agentRun->actions()
            ->where('status', AgentRunActionStatus::Proposed->value)
            ->exists();

        if (! $hasPendingApproval) {
            $agentRun->forceFill([
                'status' => AgentRunStatus::Completed,
                'completed_at' => $agentRun->completed_at ?? now(),
            ])->save();
        }
    }

    private function runResponse(AgentRun $agentRun): JsonResponse
    {
        $agentRun->load(['agent:id,name', 'manager:id,name', 'actions']);

        return response()->json([
            'agent_run' => AgentRunPresenter::toArray($agentRun),
        ]);
    }
}
