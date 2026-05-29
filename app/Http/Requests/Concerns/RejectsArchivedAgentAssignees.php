<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

trait RejectsArchivedAgentAssignees
{
    /**
     * @param  array<int, int>  $allowedArchivedAgentIds
     */
    protected function rejectArchivedAgentAssignees(
        Validator $validator,
        array $allowedArchivedAgentIds = [],
    ): void {
        $assigneeIds = $this->normalizedAssigneeIds()
            ->diff($allowedArchivedAgentIds);

        if ($assigneeIds->isEmpty()) {
            return;
        }

        $hasArchivedAgents = User::query()
            ->whereIn('id', $assigneeIds)
            ->where('is_agent', true)
            ->whereNotNull('agent_archived_at')
            ->exists();

        if ($hasArchivedAgents) {
            $validator->errors()->add(
                'assignee_ids',
                'Archived agents cannot be assigned to tasks.',
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function normalizedAssigneeIds(): Collection
    {
        return collect($this->input('assignee_ids', []))
            ->filter(fn ($id): bool => is_scalar($id) && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }
}
