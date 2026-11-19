<?php

namespace App\Mensajeria;

use App\Mensajeria\Excepciones\MensajeInvalido;
use App\Mensajeria\Excepciones\NotificacionRechazada;
use App\Models\NotificacionPedido;
use App\Models\Pedido;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Lógica del CONSUMIDOR para un mensaje: lo valida, lo procesa, actualiza la
 * tabla de estados, registra el correlation_id y decide ack o nack.
 *
 * No depende de RabbitMQ (recibe el cuerpo y si es una reentrega), por eso se
 * puede probar sin broker. El comando notificaciones:consumir aplica la decisión.
 */
final class ManejadorMensajes
{
    public function __construct(private ProcesadorNotificacion $procesador) {}

    public function manejar(string $cuerpo, bool $reentregado): Decision
    {
        $log = Log::channel('mensajeria');

        try {
            $mensaje = MensajeNotificacion::desdeJson($cuerpo);
        } catch (MensajeInvalido $e) {
            $log->error('consumidor: mensaje inválido; nack a la cola de fallidas', [
                'estado' => 'descartado',
                'motivo' => $e->getMessage(),
                'correlation_id_recibido' => $e->correlationId,
            ]);

            return Decision::EnviarAFallidas;
        }

        // Desde aquí, cada línea del registro lleva el mismo correlation_id que puso el productor.
        Context::add('correlation_id', $mensaje->correlationId);

        try {
            return $this->procesar($mensaje, $reentregado, $log);
        } finally {
            Context::forget('correlation_id');
        }
    }

    private function procesar(MensajeNotificacion $mensaje, bool $reentregado, LoggerInterface $log): Decision
    {
        $contexto = ['message_id' => $mensaje->messageId, 'pedido_id' => $mensaje->pedidoId(), 'evento' => $mensaje->evento()];

        $log->info('consumidor: mensaje recibido de la cola', $contexto + ['estado' => 'recibido', 'reentregado' => $reentregado]);

        if (! Pedido::whereKey($mensaje->pedidoId())->exists()) {
            $log->error('consumidor: el pedido del mensaje no existe; nack a la cola de fallidas', $contexto + ['estado' => 'descartado']);

            return Decision::EnviarAFallidas;
        }

        // Si otro productor publicó el mensaje, todavía no tiene fila: se crea para poder seguirlo.
        $registro = NotificacionPedido::firstWhere('message_id', $mensaje->messageId)
            ?? NotificacionPedido::registrar($mensaje, NotificacionPedido::MODO_ASINCRONO, now());

        // Idempotencia: si RabbitMQ entrega dos veces el mismo mensaje, no se envía dos veces.
        if ($registro->estado === NotificacionPedido::ENVIADA) {
            $log->info('consumidor: mensaje duplicado, ya estaba enviado; ack sin repetir el envío', $contexto + ['estado' => 'enviada']);

            return Decision::Confirmar;
        }

        $log->info('consumidor: procesando notificación', $contexto + ['estado' => 'procesando', 'intento' => $registro->intentos + 1]);

        try {
            $this->procesador->procesar($registro);
        } catch (NotificacionRechazada $e) {
            $registro->marcarFallida($e->getMessage());
            $log->warning('consumidor: no se pudo notificar (error permanente); nack a la cola de fallidas', $contexto + ['estado' => 'fallida', 'motivo' => $e->getMessage()]);

            return Decision::EnviarAFallidas;
        } catch (Throwable $e) {
            if (! $reentregado) {
                $registro->marcarParaReintento($e->getMessage());
                $log->warning('consumidor: error transitorio; nack con reencolado para un segundo intento', $contexto + ['estado' => 'en_cola', 'motivo' => $e->getMessage()]);

                return Decision::Reencolar;
            }

            $registro->marcarFallida('Falló también en el segundo intento: '.$e->getMessage());
            $log->error('consumidor: falló el segundo intento; nack a la cola de fallidas', $contexto + ['estado' => 'fallida', 'motivo' => $e->getMessage()]);

            return Decision::EnviarAFallidas;
        }

        $log->info('consumidor: notificación enviada; ack', $contexto + [
            'estado' => 'enviada',
            'destinatario' => $registro->destinatario,
            'demora_total_ms' => $registro->duracionMs(),
        ]);

        return Decision::Confirmar;
    }
}
