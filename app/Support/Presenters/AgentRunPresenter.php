<?php

namespace App\Support\Presenters;

use App\Models\AgentRun;
use App\Models\AgentRunAction;

class AgentRunPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(AgentRun $run): array
    {
        return [
            'id' => $run->id,
            'board_id' => $run->board_id,
            'task_id' => $run->task_id,
            'status' => $run->status->value,
            'autonomy' => $run->autonomy->value,
            'provider' => $run->provider->value,
            'model' => $run->model,
            'summary' => $run->summary,
            'rationale' => $run->rationale,
            'error' => [
                'code' => $run->error_code,
                'message' => $run->error_message,
            ],
            'usage' => [
                'input_tokens' => $run->input_tokens,
                'output_tokens' => $run->output_tokens,
                'total_tokens' => $run->total_tokens,
            ],
            'agent' => [
                'id' => $run->agent?->id,
                'name' => $run->agent?->name,
            ],
            'manager' => [
                'id' => $run->manager?->id,
                'name' => $run->manager?->name,
            ],
            'actions' => self::actions($run),
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'failed_at' => $run->failed_at,
            'created_at' => $run->created_at,
            'updated_at' => $run->updated_at,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function actions(AgentRun $run): array
    {
        $actions = $run->relationLoaded('actions')
            ? $run->actions
            : $run->actions()->get();

        return $actions
            ->map(fn (AgentRunAction $action): array => [
                'id' => $action->id,
                'type' => $action->type->value,
                'status' => $action->status->value,
                'payload' => $action->payload ?? [],
                'error_message' => $action->error_message,
                'approved_by' => $action->approved_by,
                'approved_at' => $action->approved_at,
                'rejected_at' => $action->rejected_at,
                'applied_at' => $action->applied_at,
                'created_at' => $action->created_at,
                'updated_at' => $action->updated_at,
            ])
            ->values()
            ->all();
    }
}
