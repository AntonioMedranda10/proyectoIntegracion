<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reglas comunes del intercambio JSON en la API.
 *
 * Entrada: si el cuerpo no se puede deserializar (JSON mal escrito), responde
 * 400 en lugar de tratarlo como una solicitud vacía.
 * Salida: serializa con tildes y barras legibles (UTF-8 sin escapar).
 */
class ContratoJson
{
    private const OPCIONES_JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function handle(Request $request, Closure $next): Response
    {
        $contenido = $request->getContent();

        if ($request->isJson() && trim($contenido) !== '') {
            json_decode($contenido);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'El cuerpo de la solicitud no es un JSON válido.',
                    'detalle' => json_last_error_msg(),
                ], 400, [], self::OPCIONES_JSON);
            }
        }

        $respuesta = $next($request);

        if ($respuesta instanceof JsonResponse) {
            $respuesta->setEncodingOptions($respuesta->getEncodingOptions() | self::OPCIONES_JSON);
        }

        return $respuesta;
    }
}
