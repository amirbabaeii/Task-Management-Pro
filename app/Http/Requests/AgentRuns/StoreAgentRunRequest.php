<?php

namespace App\Http\Requests\AgentRuns;

use App\Enums\AgentAutonomy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'autonomy' => $this->input('autonomy') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'autonomy' => ['nullable', Rule::enum(AgentAutonomy::class)],
        ];
    }

    public function autonomy(): ?AgentAutonomy
    {
        $autonomy = $this->validated('autonomy');

        return $autonomy === null ? null : AgentAutonomy::from($autonomy);
    }
}
