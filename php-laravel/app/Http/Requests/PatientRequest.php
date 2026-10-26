<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating (POST) and fully replacing (PUT) a patient.
 */
class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sanitized = [];

        foreach (['cpf' => '/\D/', 'phone' => '/\D/', 'rg' => '/[^0-9X]/'] as $field => $pattern) {
            $value = $this->input($field);

            if (is_string($value)) {
                $sanitized[$field] = preg_replace($pattern, '', strtoupper($value));
            }
        }

        $this->merge($sanitized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:255'],
            'cpf' => [
                'required',
                'string',
                new Cpf(),
                Rule::unique('patients', 'cpf')
                    ->whereNull('deleted_at')
                    ->ignore($patient instanceof Patient ? $patient->getKey() : null),
            ],
            'rg' => ['required', 'string', 'max:20'],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(Patient::SEXES)],
            'phone' => ['required', 'string', 'digits_between:10,11'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'name.min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
            'name.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'cpf.unique' => 'Já existe um paciente cadastrado com este CPF.',
            'rg.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'birth_date.date_format' => 'O campo :attribute deve ser uma data válida no formato AAAA-MM-DD.',
            'birth_date.before_or_equal' => 'O campo :attribute não pode ser uma data futura.',
            'sex.in' => 'O campo :attribute deve ser M (Masculino) ou F (Feminino).',
            'phone.digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
            'email.email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
            'email.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'description.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome completo',
            'cpf' => 'CPF',
            'rg' => 'RG',
            'birth_date' => 'data de nascimento',
            'sex' => 'sexo',
            'phone' => 'celular',
            'email' => 'e-mail',
            'description' => 'descrição',
        ];
    }

    /**
     * Body parameter documentation for Scribe (API docs at /docs).
     *
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Nome completo do paciente (3 a 255 caracteres).',
                'example' => 'Maria da Silva',
            ],
            'cpf' => [
                'description' => 'CPF válido (dígitos verificadores conferidos), com ou sem máscara. Único entre os pacientes ativos. Armazenado só com dígitos.',
                'example' => '625.189.530-60',
            ],
            'rg' => [
                'description' => 'RG, com ou sem máscara (até 20 caracteres). Armazenado só com dígitos e a letra X.',
                'example' => '35.688.019-X',
            ],
            'birth_date' => [
                'description' => 'Data de nascimento no formato AAAA-MM-DD; não pode ser futura.',
                'example' => '1996-07-20',
            ],
            'sex' => [
                'description' => 'Sexo: `M` (Masculino) ou `F` (Feminino).',
                'example' => 'F',
            ],
            'phone' => [
                'description' => 'Celular com DDD, com ou sem máscara (10 ou 11 dígitos). Armazenado só com dígitos.',
                'example' => '(11) 92853-5718',
            ],
            'email' => [
                'description' => 'E-mail do paciente (opcional).',
                'example' => 'maria@example.com',
            ],
            'description' => [
                'description' => 'Observações sobre o paciente (opcional, até 2000 caracteres).',
                'example' => 'Paciente com miopia leve, retorno anual.',
            ],
        ];
    }
}
