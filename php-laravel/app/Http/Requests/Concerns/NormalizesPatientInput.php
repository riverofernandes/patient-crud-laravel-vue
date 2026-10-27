<?php

namespace App\Http\Requests\Concerns;

/**
 * Input sanitizing and pt-BR validation texts shared by the patient form requests.
 */
trait NormalizesPatientInput
{
    /**
     * Strip masks: CPF and phone keep only digits; RG keeps digits and the letter X. Address: the CEP mask
     * (`00000-000`, dots and spaces) is removed (letters are kept, so they still fail validation) and the
     * state is upper-cased.
     */
    protected function prepareForValidation(): void
    {
        $sanitized = [];

        foreach (['cpf' => '/\D/', 'phone' => '/\D/', 'rg' => '/[^0-9X]/'] as $field => $pattern) {
            $value = $this->input($field);

            if (is_string($value)) {
                $sanitized[$field] = preg_replace($pattern, '', strtoupper($value));
            }
        }

        $address = $this->input('address');

        if (is_array($address)) {
            if (is_string($address['cep'] ?? null)) {
                $address['cep'] = preg_replace('/[\s.\-]/', '', $address['cep']);
            }

            if (is_string($address['state'] ?? null)) {
                $address['state'] = strtoupper(trim($address['state']));
            }

            $sanitized['address'] = $address;
        }

        $this->merge($sanitized);
    }

    /**
     * @return array<string, string>
     */
    protected function sharedMessages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sharedAttributes(): array
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
            'address' => 'endereço',
            'address.cep' => 'CEP',
            'address.street' => 'logradouro',
            'address.number' => 'número',
            'address.complement' => 'complemento',
            'address.neighborhood' => 'bairro',
            'address.city' => 'cidade',
            'address.state' => 'UF',
        ];
    }
}
