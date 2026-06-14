<?php

namespace App\Http\Requests\AiSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpenAiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->is_agent;
    }

    protected function prepareForValidation(): void
    {
        $apiKey = trim((string) $this->input('api_key'));

        $this->merge([
            'api_key' => $apiKey === '' ? null : $apiKey,
            'default_model' => trim((string) $this->input('default_model')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => [
                Rule::requiredIf(
                    fn (): bool => ! $this->user()
                        ?->openAiConnection()
                        ->exists(),
                ),
                'nullable',
                'string',
                'max:500',
            ],
            'default_model' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
        ];
    }
}
