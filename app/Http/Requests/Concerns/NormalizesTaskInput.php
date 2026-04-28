<?php

namespace App\Http\Requests\Concerns;

use App\Models\Task;

trait NormalizesTaskInput
{
    /**
     * Build a normalized payload of task fields the request actually carried,
     * trimming whitespace and coercing empty strings to null.
     *
     * @return array<string, mixed>
     */
    protected function normalizedTaskInput(): array
    {
        $normalized = [];

        if ($this->has('title')) {
            $normalized['title'] = trim((string) $this->input('title'));
        }

        if ($this->has('description')) {
            $description = $this->input('description');
            $normalized['description'] = filled($description) ? trim((string) $description) : null;
        }

        if ($this->has('tags')) {
            $normalized['tags'] = Task::normalizeTags($this->input('tags'));
        }

        if ($this->has('deadline_at')) {
            $normalized['deadline_at'] = $this->input('deadline_at') ?: null;
        }

        return $normalized;
    }
}
