<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // CEP lookup (GET /api/cep/{cep}) proxies ViaCEP; results (found / not found) are cached for 24 h.
    // Timeouts in whole seconds.
    'viacep' => [
        'url' => env('VIACEP_URL', 'https://viacep.com.br'),
        'timeout' => (int) env('VIACEP_TIMEOUT', 3),
        'connect_timeout' => (int) env('VIACEP_CONNECT_TIMEOUT', 2),
        'cache_ttl' => (int) env('VIACEP_CACHE_TTL', 86400),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
