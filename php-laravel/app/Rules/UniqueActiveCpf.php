<?php

namespace App\Rules;

use App\Models\Patient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The CPF must not belong to another active (not soft-deleted) patient.
 */
class UniqueActiveCpf implements ValidationRule
{
    public const MESSAGE = 'Já existe um paciente cadastrado com este CPF.';

    public function __construct(private readonly ?int $ignoreId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && Patient::cpfInUse($value, $this->ignoreId)) {
            $fail(self::MESSAGE);
        }
    }
}
