<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A body sent as JSON that does not parse is a 400, instead of being silently treated as an empty body (which
 * would turn a typo into "all fields missing" or, on PATCH, into a no-op 200). An empty body is still allowed.
 * Runs inside the api group, so the global middleware (X-Request-Id, CORS) still decorates the response.
 */
class RejectMalformedJson
{
    public const MESSAGE = 'JSON inválido.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && trim($request->getContent()) !== '') {
            try {
                json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return response()->json(['message' => self::MESSAGE], Response::HTTP_BAD_REQUEST);
            }
        }

        return $next($request);
    }
}
