<?php

namespace App\Http\Requests\Concerns;

/**
 * pt-BR validation texts shared by the consultation and eye-exam form requests (the app locale is English,
 * so every message used is spelled out, as in the patient requests).
 */
trait ClinicalValidationMessages
{
    /**
     * Messages keyed by rule: they apply to every attribute of the request.
     *
     * @return array<string, string|array<string, string>>
     */
    protected function clinicalMessages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'array' => 'O campo :attribute deve ser um objeto.',
            // Size rules take one message per attribute type.
            'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.'],
            'max' => ['string' => 'O campo :attribute não pode ter mais de :max caracteres.'],
            'between' => ['numeric' => 'O campo :attribute deve estar entre :min e :max.'],
            'multiple_of' => 'O campo :attribute deve ser múltiplo de :value.',
            'date_format' => 'O campo :attribute deve ser uma data válida no formato AAAA-MM-DD.',
        ];
    }
}
