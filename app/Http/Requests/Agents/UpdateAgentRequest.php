<?php

namespace App\Http\Requests\Agents;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'agent_title' => $this->nullableTrim('agent_title'),
            'agent_profile' => $this->nullableTrim('agent_profile'),
            'agent_personality' => $this->nullableTrim('agent_personality'),
            'agent_skills' => Task::normalizeTags($this->input('agent_skills')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $agent = $this->route('agent');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($agent instanceof User ? $agent->id : null),
            ],
            'agent_title' => ['nullable', 'string', 'max:120'],
            'agent_profile' => ['nullable', 'string', 'max:1000'],
            'agent_personality' => ['nullable', 'string', 'max:1000'],
            'agent_skills' => ['array', 'max:12'],
            'agent_skills.*' => ['string', 'max:40'],
        ];
    }

    private function nullableTrim(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
