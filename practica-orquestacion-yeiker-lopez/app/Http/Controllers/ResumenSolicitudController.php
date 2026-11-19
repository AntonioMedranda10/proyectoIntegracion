<?php

namespace App\Http\Controllers;

use App\Exceptions\DependenciaNoDisponible;
use App\Services\OrquestadorSolicitudes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResumenSolicitudController extends Controller
{
    /** GET /api/resumen-solicitud/{id} → 200 con el resumen | 502 si un servicio falla */
    public function __invoke(Request $request, int $id, OrquestadorSolicitudes $orquestador): JsonResponse
    {
        $correlationId = $request->attributes->get('correlation_id');
        $registro = Log::channel('orquestacion');

        try {
            $resumen = $orquestador->resumen($id, $correlationId);
            $registro->info('Orquestación completada', ['solicitud_id' => $id, 'ms' => $orquestador->tiempos()]);

            return response()->json($resumen);
        } catch (DependenciaNoDisponible $falla) {
            // El detalle técnico queda en storage/logs/orquestacion.log;
            // el cliente recibe un mensaje controlado, sin trazas ni direcciones internas.
            $registro->warning('Falla de orquestación', [
                'solicitud_id' => $id,
                'servicio' => $falla->servicio,
                'motivo' => $falla->motivo,
                'url' => $falla->url,
                'ms' => $falla->milisegundos,
            ]);

            return response()->json([
                'error' => "No se pudo armar el resumen: el servicio de {$falla->servicio} no está disponible.",
                'servicio' => $falla->servicio,
                'correlation_id' => $correlationId,
            ], 502);
        }
    }
}
