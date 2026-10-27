<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Request tracing (first global middleware, so every response carries it: 404, 405, 413, 429, preflight...):
 * - reuses the incoming X-Request-Id when it is safe (UUID or up to 64 chars of [A-Za-z0-9-_.]), otherwise
 *   generates a UUID;
 * - adds it to every log record of the request (Log::withContext) and to the response header;
 * - writes one access log line per request: method, route pattern (or path) WITHOUT the query string (search
 *   filters may carry a CPF), status, duration and request id. Never the body nor the query.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    private const VALID = '/^[A-Za-z0-9\-_.]{1,64}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $incoming = $request->headers->get(self::HEADER);
        $id = is_string($incoming) && preg_match(self::VALID, $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->headers->set(self::HEADER, $id);
        Log::withContext(['request_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        Log::info('http.request', [
            'method' => $request->getMethod(),
            // Route pattern ("api/patients/{patient}") when matched; plain path (never the query) otherwise.
            'route' => $request->route()?->uri() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $start) * 1000, 1),
        ]);

        return $response;
    }
}
