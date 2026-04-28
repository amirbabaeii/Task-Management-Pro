<?php

namespace App\Http\Requests\Boards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name')) {
            $payload['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('description')) {
            $payload['description'] = trim((string) $this->input('description')) ?: null;
        }

        $this->merge($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:280'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('name') && ! $this->has('description')) {
                $validator->errors()->add(
                    'board',
                    'Provide a board name or description to update.',
                );
            }
        });
    }
}
