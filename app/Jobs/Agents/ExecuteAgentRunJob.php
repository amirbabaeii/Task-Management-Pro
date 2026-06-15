<?php

namespace App\Jobs\Agents;

use App\Actions\AgentRuns\ApplyAgentRunAction;
use App\Enums\AgentAutonomy;
use App\Enums\AgentProviderErrorCode;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use App\Enums\AgentRunStatus;
use App\Exceptions\Agents\AgentProviderException;
use App\Models\AgentRun;
use App\Notifications\AgentRunApprovalRequiredNotification;
use App\Notifications\AgentRunFailedNotification;
use App\Services\Ai\AgentProviderManager;
use App\Services\Ai\Data\AgentRunPrompt;
use App\Services\Ai\Data\AgentRunResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExecuteAgentRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public AgentRun $run,
    ) {
        $this->timeout = (int) config('ai.execution.queue_timeout');
        $this->onQueue((string) config('ai.execution.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_map(
            fn ($seconds): int => (int) $seconds,
            (array) config('ai.execution.retry_backoff', [30, 120, 300]),
        );
    }

    public function handle(AgentProviderManager $providers, ApplyAgentRunAction $applyAction): void
    {
        $run = AgentRun::query()
            ->with('providerConnection')
            ->find($this->run->id);

        if (! $run || $run->status !== AgentRunStatus::Queued) {
            return;
        }

        $this->markRunning($run);

        if ($run->providerConnection === null) {
            $this->markFailed(
                $run,
                AgentProviderErrorCode::ProviderError->value,
                'The configured provider connection is no longer available.',
            );

            return;
        }

        try {
            $result = $providers
                ->for($run->provider)
                ->execute($run->providerConnection, $this->promptFor($run));

            $this->persistResult($run, $result, $applyAction);
        } catch (AgentProviderException $exception) {
            if ($exception->retryable && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $this->markFailed(
                $run,
                $exception->errorCode->value,
                $exception->getMessage(),
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->markFailed(
                $run,
                AgentProviderErrorCode::ProviderError->value,
                'The agent run failed unexpectedly.',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = AgentRun::query()->find($this->run->id);

        if (! $run || ! $run->status->isActive()) {
            return;
        }

        if ($exception instanceof AgentProviderException) {
            $this->markFailed(
                $run,
                $exception->errorCode->value,
                $exception->getMessage(),
            );

            return;
        }

        $this->markFailed(
            $run,
            AgentProviderErrorCode::ProviderError->value,
            'The agent run failed unexpectedly.',
        );
    }

    private function markRunning(AgentRun $run): void
    {
        $run->forceFill([
            'status' => AgentRunStatus::Running,
            'started_at' => $run->started_at ?? now(),
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    private function promptFor(AgentRun $run): AgentRunPrompt
    {
        return new AgentRunPrompt(
            model: $run->model,
            systemInstructions: $this->systemInstructions(),
            context: $run->context_snapshot ?? [],
        );
    }

    private function systemInstructions(): string
    {
        return implode("\n", [
            'You are an AI teammate working inside Task Management Pro.',
            'Return only the requested structured result.',
            'Allowed actions are add_comment, add_checklist_item, toggle_checklist_item, update_progress, change_status, and update_task_fields.',
            'For update_task_fields, only suggest title, description, tags, priority, and deadline_at.',
            'Never assign users, archive, delete, move tasks between boards, manage board members, or change provider settings.',
        ]);
    }

    private function persistResult(
        AgentRun $run,
        AgentRunResult $result,
        ApplyAgentRunAction $applyAction,
    ): void {
        $automaticActionIds = DB::transaction(function () use ($run, $result): array {
            $run->forceFill([
                'summary' => $result->summary,
                'rationale' => $result->rationale,
                'provider_response_id' => $result->providerResponseId,
                'input_tokens' => $result->usage['input_tokens'],
                'output_tokens' => $result->usage['output_tokens'],
                'total_tokens' => $result->usage['total_tokens'],
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            $automaticActionIds = [];

            foreach ($result->actions as $action) {
                $type = AgentRunActionType::tryFrom((string) ($action['type'] ?? ''));

                if ($type === null) {
                    throw new AgentProviderException(
                        AgentProviderErrorCode::MalformedOutput,
                        'The provider returned an unsupported action type.',
                    );
                }

                $created = $run->actions()->create([
                    'type' => $type,
                    'status' => $this->storedActionStatus($run),
                    'payload' => Arr::except($action, ['type']),
                ]);

                if ($this->shouldApplyAutomatically($run, $type)) {
                    $automaticActionIds[] = $created->id;
                }
            }

            return $automaticActionIds;
        });

        foreach ($automaticActionIds as $actionId) {
            $action = $run->actions()->find($actionId);

            if ($action) {
                $applyAction->execute($action);
            }
        }

        $this->finishRun($run);
    }

    private function storedActionStatus(AgentRun $run): AgentRunActionStatus
    {
        return $run->autonomy === AgentAutonomy::Advisory
            ? AgentRunActionStatus::Suggested
            : AgentRunActionStatus::Proposed;
    }

    private function shouldApplyAutomatically(AgentRun $run, AgentRunActionType $type): bool
    {
        return $run->autonomy === AgentAutonomy::Automatic
            && $type->canApplyAutomatically();
    }

    private function finishRun(AgentRun $run): void
    {
        $run->refresh();

        $hasPendingApproval = $run->actions()
            ->where('status', AgentRunActionStatus::Proposed->value)
            ->exists();

        $run->forceFill([
            'status' => $hasPendingApproval
                ? AgentRunStatus::AwaitingApproval
                : AgentRunStatus::Completed,
            'completed_at' => now(),
        ])->save();

        if ($run->status === AgentRunStatus::AwaitingApproval) {
            $run->loadMissing('manager');
            $run->manager?->notify(new AgentRunApprovalRequiredNotification($run));
        }
    }

    private function markFailed(AgentRun $run, string $code, string $message): void
    {
        $run->forceFill([
            'status' => AgentRunStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'failed_at' => now(),
        ])->save();

        $run->loadMissing('manager');
        $run->manager?->notify(new AgentRunFailedNotification($run));
    }
}
