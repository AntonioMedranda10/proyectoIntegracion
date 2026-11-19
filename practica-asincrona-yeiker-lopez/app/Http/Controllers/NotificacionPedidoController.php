<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificacionPedidoRequest;
use App\Http\Resources\NotificacionResource;
use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\Excepciones\NotificacionRechazada;
use App\Mensajeria\MensajeNotificacion;
use App\Mensajeria\ProcesadorNotificacion;
use App\Mensajeria\PublicadorMensajes;
use App\Models\NotificacionPedido;
use App\Models\Pedido;
use App\Support\Fecha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class NotificacionPedidoController extends Controller
{
    /**
     * ASÍNCRONO (productor): valida, publica en la cola y responde 202 Accepted
     * sin esperar el envío. El consumidor hará el trabajo después.
     */
    public function encolar(NotificacionPedidoRequest $request, PublicadorMensajes $publicador): JsonResponse
    {
        $recibidoEn = now();
        $mensaje = $this->crearMensaje($request, $recibidoEn);
        $registro = NotificacionPedido::registrar($mensaje, NotificacionPedido::MODO_ASINCRONO, $recibidoEn);

        $log = Log::channel('mensajeria');
        $contexto = ['message_id' => $mensaje->messageId, 'pedido_id' => $mensaje->pedidoId(), 'evento' => $mensaje->evento()];
        $log->info('productor: solicitud recibida y validada', $contexto + ['estado' => 'recibida']);

        try {
            $publicador->publicar($mensaje);
        } catch (BrokerNoDisponible $e) {
            $registro->marcarNoPublicada($e->getMessage());
            $log->error('productor: el broker no confirmó el mensaje; no quedó en cola', $contexto + ['estado' => 'no_publicada', 'motivo' => $e->getMessage()]);

            return response()->json([
                'error' => 'El servicio de mensajería no está disponible. La notificación NO quedó en cola; intente de nuevo más tarde.',
                'estado' => NotificacionPedido::NO_PUBLICADA,
                'messageId' => $mensaje->messageId,
                'correlationId' => $mensaje->correlationId,
            ], 503);
        }

        $log->info('productor: mensaje publicado en la cola', $contexto + ['estado' => 'en_cola', 'cola' => config('mensajeria.cola')]);

        $enlace = route('notificaciones.mostrar', $mensaje->messageId);

        return response()->json([
            'estado' => NotificacionPedido::EN_COLA,
            'mensaje' => 'Solicitud recibida. La notificación se procesará en segundo plano; consulte su estado en "consultarEstado".',
            'messageId' => $mensaje->messageId,
            'correlationId' => $mensaje->correlationId,
            'pedidoId' => $mensaje->pedidoId(),
            'evento' => $mensaje->evento(),
            'cola' => config('mensajeria.cola'),
            'recibidoEn' => Fecha::iso($recibidoEn),
            'consultarEstado' => $enlace,
        ], 202)->header('Location', $enlace);
    }

    /**
     * SÍNCRONO (para comparar): hace el mismo trabajo dentro de la petición.
     * El cliente no recibe respuesta hasta que termina el envío.
     */
    public function enviarAhora(NotificacionPedidoRequest $request, ProcesadorNotificacion $procesador): JsonResponse
    {
        $recibidoEn = now();
        $mensaje = $this->crearMensaje($request, $recibidoEn);
        $registro = NotificacionPedido::registrar($mensaje, NotificacionPedido::MODO_SINCRONO, $recibidoEn);

        $log = Log::channel('mensajeria');
        $contexto = ['message_id' => $mensaje->messageId, 'pedido_id' => $mensaje->pedidoId(), 'evento' => $mensaje->evento()];
        $log->info('sincrono: solicitud recibida; el cliente espera hasta que termine el envío', $contexto + ['estado' => 'procesando']);

        try {
            $procesador->procesar($registro);
        } catch (NotificacionRechazada $e) {
            $registro->marcarFallida($e->getMessage());
            $log->warning('sincrono: no se pudo notificar', $contexto + ['estado' => 'fallida', 'motivo' => $e->getMessage()]);

            return response()->json([
                'error' => $e->getMessage(),
                'estado' => NotificacionPedido::FALLIDA,
                'messageId' => $mensaje->messageId,
                'correlationId' => $mensaje->correlationId,
            ], 422);
        }

        $log->info('sincrono: notificación enviada; recién ahora se responde al cliente', $contexto + [
            'estado' => 'enviada',
            'demora_total_ms' => $registro->duracionMs(),
        ]);

        return (new NotificacionResource($registro))->response()->setStatusCode(200);
    }

    /** Estado de un mensaje (tabla de estados). */
    public function mostrar(string $messageId): NotificacionResource
    {
        return new NotificacionResource(NotificacionPedido::where('message_id', $messageId)->firstOrFail());
    }

    /** Lista de la tabla de estados; ?correlationId=... filtra una operación. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'correlationId' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}$/D'],
        ], [
            'correlationId.regex' => 'El correlationId solo admite letras, números, puntos y guiones (máximo 100).',
        ]);

        $registros = NotificacionPedido::query()
            ->when($datos['correlationId'] ?? null, fn ($q, $id) => $q->where('correlation_id', $id))
            ->latest('id')
            ->limit(50)
            ->get();

        return NotificacionResource::collection($registros);
    }

    private function crearMensaje(NotificacionPedidoRequest $request, $recibidoEn): MensajeNotificacion
    {
        return MensajeNotificacion::paraPedido(
            Pedido::findOrFail($request->validated('pedidoId')),
            $request->validated('evento'),
            // Lo generó o lo preservó el middleware CorrelationId (cabecera X-Correlation-ID).
            $request->attributes->get('correlation_id'),
            $recibidoEn,
        );
    }
}
