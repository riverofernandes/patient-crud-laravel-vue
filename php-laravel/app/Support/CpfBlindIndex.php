<?php

namespace App\Support;

use Illuminate\Encryption\MissingAppKeyException;

/**
 * Deterministic, keyed hash of a CPF (blind index): lets the encrypted CPF be looked up by equality
 * without storing it in clear. See config/patients.php for the key and rotation notes.
 */
final class CpfBlindIndex
{
    private const KEY_DERIVATION_LABEL = 'patients.cpf_blind_index.v1';

    /**
     * HMAC-SHA256 (64 hex chars) of the CPF digits; masks are ignored, so "625.189.530-60" and
     * "62518953060" produce the same hash.
     */
    public static function hash(string $cpf): string
    {
        return hash_hmac('sha256', (string) preg_replace('/\D/', '', $cpf), self::key());
    }

    private static function key(): string
    {
        $configured = config('patients.cpf_hash_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = config('app.key');

        if (! is_string($appKey) || $appKey === '') {
            throw new MissingAppKeyException();
        }

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = (string) base64_decode(substr($appKey, 7), true);
        }

        // Derived sub-key: the encryption key itself is never used as the HMAC key.
        return hash_hmac('sha256', self::KEY_DERIVATION_LABEL, $appKey, true);
    }
}
