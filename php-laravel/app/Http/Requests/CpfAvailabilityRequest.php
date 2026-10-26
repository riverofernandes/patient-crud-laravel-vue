<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPatientInput;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters of the CPF availability check (same CPF format validation as the patient form).
 */
class CpfAvailabilityRequest extends FormRequest
{
    use NormalizesPatientInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cpf' => ['required', 'string', new Cpf()],
            'ignore' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->sharedMessages() + [
            'ignore.integer' => 'O campo :attribute deve ser um número inteiro.',
            'ignore.min' => 'O campo :attribute deve ser no mínimo :min.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->sharedAttributes() + ['ignore' => 'paciente ignorado'];
    }

    public function ignoredId(): ?int
    {
        return $this->filled('ignore') ? (int) $this->input('ignore') : null;
    }

    /**
     * Query parameter documentation for Scribe (API docs at /docs).
     *
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'cpf' => [
                'description' => 'CPF a verificar, com ou sem máscara. Deve ser válido (dígitos verificadores conferidos).',
                'example' => '625.189.530-60',
            ],
            'ignore' => [
                'description' => 'ID de um paciente a desconsiderar (use na edição para não acusar o próprio CPF).',
                'example' => 1,
            ],
        ];
    }
}
