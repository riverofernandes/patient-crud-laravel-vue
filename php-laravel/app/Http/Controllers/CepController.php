<?php

namespace App\Http\Controllers;

use App\Support\CepLookup;
use App\Support\CepServiceUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @group Endereço
 *
 * Consulta de CEP para preencher o endereço do paciente.
 */
class CepController extends Controller
{
    public const INVALID_MESSAGE = 'O CEP deve ter 8 dígitos.';

    public const NOT_FOUND_MESSAGE = 'CEP não encontrado.';

    public const UNAVAILABLE_MESSAGE = 'Serviço de CEP indisponível. Preencha o endereço manualmente.';

    /**
     * Consultar CEP
     *
     * Busca o endereço de um CEP no ViaCEP (resultado em cache por 24 horas). Aceita máscara (`01310-100`).
     * Campos vazios no ViaCEP (CEP geral de cidade pequena) voltam como texto vazio. `503` quando o ViaCEP não
     * responde: o endereço pode ser preenchido manualmente.
     *
     * @urlParam code string required CEP com 8 dígitos, com ou sem máscara. Example: 01310-100
     *
     * @response 200 scenario="CEP encontrado" {"data": {"cep": "01310100", "street": "Avenida Paulista", "neighborhood": "Bela Vista", "city": "São Paulo", "state": "SP"}}
     * @response 404 scenario="CEP inexistente" {"message": "CEP não encontrado."}
     * @response 422 scenario="Formato inválido" {"message": "O CEP deve ter 8 dígitos.", "errors": {"cep": ["O CEP deve ter 8 dígitos."]}}
     * @response 503 scenario="ViaCEP indisponível" {"message": "Serviço de CEP indisponível. Preencha o endereço manualmente."}
     */
    public function show(string $code): JsonResponse
    {
        $digits = (string) preg_replace('/[\s.\-]/', '', $code);

        if (preg_match('/^\d{8}$/', $digits) !== 1) {
            return response()->json([
                'message' => self::INVALID_MESSAGE,
                'errors' => ['cep' => [self::INVALID_MESSAGE]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $address = CepLookup::find($digits);
        } catch (CepServiceUnavailable $e) {
            // No message/URL: it would carry the CEP.
            Log::warning('CEP lookup failed', ['reason' => class_basename($e->getPrevious() ?? $e)]);

            return response()->json(['message' => self::UNAVAILABLE_MESSAGE], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($address === CepLookup::NOT_FOUND) {
            return response()->json(['message' => self::NOT_FOUND_MESSAGE], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $address]);
    }
}
