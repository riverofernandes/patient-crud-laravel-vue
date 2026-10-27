<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPatientInput;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Rules\Cpf;
use App\Rules\UniqueActiveCpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating (POST) and fully replacing (PUT) a patient.
 */
class PatientRequest extends FormRequest
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
        $patient = $this->route('patient');
        // Static keys (Scribe reads them with no input); the subfields are required only when an address object
        // is sent. An omitted or null address skips them (and, on PUT, removes the stored address).
        $requiredInAddress = Rule::requiredIf(fn () => is_array($this->input('address')));

        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:255'],
            'cpf' => [
                'required',
                'string',
                new Cpf(),
                new UniqueActiveCpf($patient instanceof Patient ? $patient->getKey() : null),
            ],
            'rg' => ['required', 'string', 'max:20'],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(Patient::SEXES)],
            'phone' => ['required', 'string', 'digits_between:10,11'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'array'],
            'address.cep' => [$requiredInAddress, 'string', 'digits:8'],
            'address.street' => [$requiredInAddress, 'string', 'max:255'],
            'address.number' => [$requiredInAddress, 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:100'],
            'address.neighborhood' => [$requiredInAddress, 'string', 'max:100'],
            'address.city' => [$requiredInAddress, 'string', 'max:100'],
            'address.state' => [$requiredInAddress, 'string', Rule::in(PatientAddress::STATES)],
        ];
    }

    /**
     * Validated patient columns (without the address).
     *
     * @return array<string, mixed>
     */
    public function patientData(): array
    {
        $data = $this->validated();
        unset($data['address']);

        return $data;
    }

    /**
     * Validated address (only the known fields, complement defaulting to null), or null when omitted/null.
     *
     * @return array<string, string|null>|null
     */
    public function addressData(): ?array
    {
        $address = $this->validated('address');

        if (! is_array($address)) {
            return null;
        }

        $data = [];

        foreach (PatientAddress::FIELDS as $field) {
            $value = $address[$field] ?? null;
            $data[$field] = $value === null ? null : (string) $value;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->sharedMessages() + [
            'name.min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
            'name.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'rg.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'birth_date.date_format' => 'O campo :attribute deve ser uma data válida no formato AAAA-MM-DD.',
            'birth_date.before_or_equal' => 'O campo :attribute não pode ser uma data futura.',
            'sex.in' => 'O campo :attribute deve ser M (Masculino) ou F (Feminino).',
            'phone.digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
            'email.email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
            'email.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'description.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.array' => 'O campo :attribute deve ser um objeto.',
            'address.cep.digits' => 'O campo :attribute deve ter 8 dígitos.',
            'address.street.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.number.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.complement.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.neighborhood.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.city.max' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'address.state.in' => 'O campo :attribute deve ser a sigla de um estado brasileiro (ex.: SP).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->sharedAttributes();
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
            'address' => [
                'description' => 'Endereço (opcional, objeto ou `null`). No PUT, omitido ou `null` remove o endereço. Quando enviado, os subcampos (exceto `complement`) são obrigatórios. Logradouro, número, complemento e bairro são armazenados criptografados.',
            ],
            'address.cep' => [
                'description' => 'CEP com 8 dígitos, com ou sem máscara (`00000-000`). Armazenado só com dígitos. Obrigatório quando `address` é enviado.',
                'example' => '01310-100',
            ],
            'address.street' => [
                'description' => 'Logradouro (até 255). Obrigatório quando `address` é enviado.',
                'example' => 'Avenida Paulista',
            ],
            'address.number' => [
                'description' => 'Número (até 20; aceita "S/N"). Obrigatório quando `address` é enviado.',
                'example' => '1578',
            ],
            'address.complement' => [
                'description' => 'Complemento (opcional, até 100).',
                'example' => 'Sala 12',
            ],
            'address.neighborhood' => [
                'description' => 'Bairro (até 100). Obrigatório quando `address` é enviado.',
                'example' => 'Bela Vista',
            ],
            'address.city' => [
                'description' => 'Cidade (até 100). Obrigatório quando `address` é enviado.',
                'example' => 'São Paulo',
            ],
            'address.state' => [
                'description' => 'UF: sigla de estado brasileiro (aceita minúsculas; armazenada em maiúsculas). Obrigatório quando `address` é enviado.',
                'example' => 'SP',
            ],
        ];
    }
}
