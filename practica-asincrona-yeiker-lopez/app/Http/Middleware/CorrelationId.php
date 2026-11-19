<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica cada operación con un X-Correlation-ID.
 *
 * - Si el cliente envía uno válido, se PROPAGA; si no envía o es inválido, se GENERA (UUID).
 * - Queda disponible para el controlador y se agrega solo a cada línea del registro (Context).
 * - Vuelve al cliente en la cabecera de la respuesta, también cuando hay error.
 */
class CorrelationId
{
    public const CABECERA = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $recibido = $request->header(self::CABECERA);

        // Entre 1 y 100 letras, números, puntos, guiones o guiones bajos: evita
        // que un valor arbitrario termine en los registros o en otras cabeceras.
        $correlationId = is_string($recibido) && preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $recibido)
            ? $recibido
            : (string) Str::uuid();

        $request->attributes->set('correlation_id', $correlationId);
        Context::add('correlation_id', $correlationId);

        $respuesta = $next($request);
        $respuesta->headers->set(self::CABECERA, $correlationId);

        return $respuesta;
    }
}
