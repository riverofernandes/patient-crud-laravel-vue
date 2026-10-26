<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Monitoramento
 */
class HealthController extends Controller
{
    /**
     * Verificar saúde da API
     *
     * Usado por healthchecks de container e monitoramento. Verifica a conexão com o banco de dados.
     * Não sofre limite de requisições (throttle).
     *
     * @response 200 scenario="API e banco disponíveis" {"status": "ok"}
     * @response 503 scenario="Banco de dados indisponível" {"status": "unavailable", "message": "Banco de dados indisponível."}
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');
        } catch (Throwable $e) {
            Log::error('Health check failed: database unreachable.', ['exception' => $e->getMessage()]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Banco de dados indisponível.',
            ], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
