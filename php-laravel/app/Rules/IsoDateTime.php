<?php

namespace App\Rules;

use App\Support\LocalDateTime;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ISO-8601 date-time, with or without offset (without = America/Sao_Paulo). See App\Support\LocalDateTime.
 */
class IsoDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! LocalDateTime::isValid($value)) {
            $fail('O campo :attribute deve ser uma data e hora válida no formato ISO-8601 (AAAA-MM-DDTHH:MM, com ou sem fuso).');
        }
    }
}
