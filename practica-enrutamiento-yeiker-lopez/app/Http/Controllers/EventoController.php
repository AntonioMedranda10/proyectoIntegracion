<?php

namespace App\Http\Controllers;

use App\Enrutamiento\CanalDeEntrada;
use App\Enrutamiento\Destino;
use App\Enrutamiento\EnrutadorPorContenido;
use App\Enrutamiento\Evento;
use App\Enrutamiento\FiltroDeMensajes;
use App\Http\Requests\EventoRequest;
use App\Http\Resources\EventoResource;
use App\Models\EventoRecibido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventoController extends Controller
{
    /**
     * Canal de entrada. Respuestas:
     *   202 enrutado      → publicado SOLO en la cola de su consumidor
     *   202 no_enrutable  → tipo desconocido, publicado en la cola de revisión
     *   200 filtrado      → rechazo por regla de negocio; no llega a ningún consumidor
     *   503 error_transporte → el broker no confirmó (fallo técnico)
     *   422               → no cumple el contrato (lo responde EventoRequest)
     */
    public function recibir(EventoRequest $request, CanalDeEntrada $canal): JsonResponse
    {
        // "datos" se conserva COMPLETO: validated() descartaría los campos sin regla
        // (por ejemplo, el comentario de un tipo desconocido que alguien debe revisar).
        $validado = ['datos' => (array) $request->input('datos', [])] + $request->validated();
        $evento = Evento::desdeEntrada($validado, $request->attributes->get('correlation_id'), now());
        $resultado = $canal->recibir($evento);
        $resumen = ['id' => $evento->id, 'tipo' => $evento->tipo, 'prioridad' => $evento->prioridad];
        $recorrido = route('eventos.mostrar', $evento->id);

        return match ($resultado->decision) {
            EventoRecibido::ENRUTADO => response()->json([
                'decision' => 'enrutado',
                'regla' => $resultado->enrutamiento->regla,
                'destino' => $resultado->enrutamiento->destino->value,
                'cola' => $resultado->enrutamiento->destino->cola(),
                'consumidor' => $resultado->enrutamiento->destino->consumidor(),
                'mensaje' => "Evento aceptado por el filtro y enviado solo a la cola de {$resultado->enrutamiento->destino->value}.",
                'evento' => $resumen,
                'correlationId' => $evento->correlationId,
                'consultarRecorrido' => $recorrido,
            ], 202),

            EventoRecibido::NO_ENRUTABLE => response()->json([
                'decision' => 'no_enrutable',
                'regla' => $resultado->enrutamiento->regla,
                'destino' => Destino::NoEnrutables->value,
                'cola' => Destino::NoEnrutables->cola(),
                'mensaje' => "Tipo «{$evento->tipo}» no reconocido: el evento no se descarta, queda en la cola de revisión.",
                'evento' => $resumen,
                'correlationId' => $evento->correlationId,
                'consultarRecorrido' => $recorrido,
            ], 202),

            EventoRecibido::FILTRADO => response()->json([
                'decision' => 'filtrado',
                'regla' => $resultado->filtro->regla,
                'motivo' => $resultado->filtro->motivo,
                'mensaje' => 'El evento no se envió a ningún consumidor. Es un rechazo por regla de negocio, no un error.',
                'evento' => $resumen,
                'correlationId' => $evento->correlationId,
                'consultarRecorrido' => $recorrido,
            ], 200),

            EventoRecibido::ERROR_TRANSPORTE => response()->json([
                'decision' => 'error_transporte',
                'error' => 'No se pudo entregar el evento al broker de mensajería. No es un rechazo de negocio: reintente más tarde.',
                'destinoPrevisto' => $resultado->enrutamiento->destino->value,
                'evento' => $resumen,
                'correlationId' => $evento->correlationId,
            ], 503),
        };
    }

    /** Recorrido registrado; ?correlationId= y ?decision= filtran. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'correlationId' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/D'],
            'decision' => ['sometimes', 'in:enrutado,no_enrutable,filtrado,error_transporte'],
        ]);

        $eventos = EventoRecibido::query()
            ->when($filtros['correlationId'] ?? null, fn ($q, $id) => $q->where('correlation_id', $id))
            ->when($filtros['decision'] ?? null, fn ($q, $d) => $q->where('decision', $d))
            ->latest('id')
            ->limit(50)
            ->get();

        return EventoResource::collection($eventos);
    }

    /** Todas las veces que llegó ese id (si llegó duplicado, aparecen las dos). */
    public function mostrar(int $eventoId): AnonymousResourceCollection
    {
        $registros = EventoRecibido::where('evento_id', $eventoId)->oldest('id')->get();
        abort_if($registros->isEmpty(), 404);

        return EventoResource::collection($registros);
    }

    /** Tabla de reglas: las mismas que aplica el código. */
    public function reglas(): JsonResponse
    {
        $enrutador = [...EnrutadorPorContenido::REGLAS, EnrutadorPorContenido::REGLA_NO_RECONOCIDO];

        return response()->json([
            'orden' => 'validación → filtro (Message Filter) → enrutador (Content-Based Router) → cola del consumidor',
            'filtro' => array_map(fn (array $r) => [
                'regla' => $r['regla'],
                'condicion' => $r['condicion'],
                'accion' => $r['accion'],
                'justificacion' => $r['justificacion'],
            ], FiltroDeMensajes::REGLAS),
            'enrutador' => array_map(fn (array $r) => [
                'regla' => $r['regla'],
                'condicion' => "tipo = {$r['tipo']}",
                'destino' => $r['destino']->value,
                'cola' => $r['destino']->cola(),
                'consumidor' => $r['destino']->consumidor(),
                'justificacion' => $r['justificacion'],
            ], $enrutador),
            'prioridadAmqp' => config('enrutamiento.prioridades'),
        ]);
    }
}
