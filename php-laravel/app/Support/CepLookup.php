<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * CEP lookup through ViaCEP (https://viacep.com.br/ws/{cep}/json/), with short timeouts and a 24 h cache.
 * Found and not-found answers are cached; failures (timeout, 5xx, unexpected body) are not.
 */
final class CepLookup
{
    public const NOT_FOUND = 'not_found';

    private const CACHE_PREFIX = 'cep:';

    /**
     * @return array{cep: string, street: string, neighborhood: string, city: string, state: string}|self::NOT_FOUND
     *
     * @throws CepServiceUnavailable
     */
    public static function find(string $cep): array|string
    {
        $key = self::CACHE_PREFIX.$cep;
        $cached = Cache::get($key);

        if ($cached === self::NOT_FOUND || is_array($cached)) {
            /** @var array{cep: string, street: string, neighborhood: string, city: string, state: string}|self::NOT_FOUND $cached */
            return $cached;
        }

        $result = self::fetch($cep);
        Cache::put($key, $result, (int) config('services.viacep.cache_ttl'));

        return $result;
    }

    /**
     * @return array{cep: string, street: string, neighborhood: string, city: string, state: string}|self::NOT_FOUND
     *
     * @throws CepServiceUnavailable
     */
    private static function fetch(string $cep): array|string
    {
        try {
            $response = Http::baseUrl((string) config('services.viacep.url'))
                ->acceptJson()
                ->timeout((int) config('services.viacep.timeout'))
                ->connectTimeout((int) config('services.viacep.connect_timeout'))
                ->get("ws/{$cep}/json/")
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new CepServiceUnavailable($e->getMessage(), 0, $e);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new CepServiceUnavailable('Unexpected ViaCEP response.');
        }

        // ViaCEP answers 200 {"erro": true} (older versions: "true") for a well-formed CEP that does not exist.
        if (array_key_exists('erro', $body)) {
            return self::NOT_FOUND;
        }

        $state = $body['uf'] ?? null;

        if (! is_string($state) || $state === '') {
            throw new CepServiceUnavailable('Unexpected ViaCEP response.');
        }

        return [
            'cep' => (string) preg_replace('/\D/', '', self::text($body['cep'] ?? $cep)),
            'street' => self::text($body['logradouro'] ?? ''),
            'neighborhood' => self::text($body['bairro'] ?? ''),
            'city' => self::text($body['localidade'] ?? ''),
            'state' => strtoupper($state),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
