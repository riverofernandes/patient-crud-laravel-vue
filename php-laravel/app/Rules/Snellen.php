<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Visual acuity in Snellen notation "20/N", N between 10 and 400 (no leading zeros).
 */
class Snellen implements ValidationRule
{
    public const MIN = 10;

    public const MAX = 400;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = is_string($value)
            && preg_match('/^20\/([1-9]\d{1,2})$/', $value, $parts) === 1
            && (int) $parts[1] >= self::MIN
            && (int) $parts[1] <= self::MAX;

        if (! $valid) {
            $fail('O campo :attribute deve estar no formato Snellen 20/N, com N entre '.self::MIN.' e '.self::MAX.'.');
        }
    }
}
