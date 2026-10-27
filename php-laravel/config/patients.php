<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CPF blind index key
    |--------------------------------------------------------------------------
    |
    | CPF, RG and phone are stored encrypted with APP_KEY (non-deterministic ciphertext), so CPF lookups
    | (uniqueness, availability, restore and search) use `patients.cpf_hash`: HMAC-SHA256 of the CPF digits.
    |
    | When CPF_HASH_KEY is empty the HMAC key is derived from APP_KEY (HMAC of a fixed label), so the index
    | never reuses the encryption key directly. Key rotation:
    |   - Changing APP_KEY makes every encrypted column unreadable (Laravel 10 has no previous-keys support):
    |     decrypt with the old key and re-encrypt with the new one before switching.
    |   - Changing the HMAC key (APP_KEY when CPF_HASH_KEY is empty, or CPF_HASH_KEY itself) invalidates
    |     every cpf_hash: run `php artisan patients:rehash-cpf` right after the switch (it recomputes the
    |     hashes from the decrypted CPFs). Until then CPF search/uniqueness checks miss existing patients.
    |   Setting a dedicated CPF_HASH_KEY decouples the two rotations.
    |
    */

    'cpf_hash_key' => env('CPF_HASH_KEY'),

];
