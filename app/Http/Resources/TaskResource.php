<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'progress' => $this->progress,
            'tags' => $this->tags ?? [],
            'deadline_at' => $this->deadline_at,
            'assignees' => $this->whenLoaded(
                'assignees',
                fn () => $this->assignees
                    ->map(fn ($assignee): array => [
                        'id' => $assignee->id,
                        'name' => $assignee->name,
                        'email' => $assignee->email,
                    ])
                    ->values()
                    ->all(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
