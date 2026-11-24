<?php

namespace App\Enrutamiento\RabbitMq;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\PublicadorEventos;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Publica el evento en el exchange "tienda.eventos" con la routing key del
 * destino que eligió el enrutador, y espera la confirmación del broker.
 */
final class PublicadorRabbitMq implements PublicadorEventos
{
    public function publicar(Evento $evento, Destino $destino): void
    {
        $conexion = ConexionRabbitMq::abrir('api-tienda (canal de entrada)');
        $canal = null;
        $confirmado = null;
        $devuelto = false;

        try {
            $canal = $conexion->channel();
            ConexionRabbitMq::declararTopologia($canal);
            $canal->confirm_select();
            $canal->set_ack_handler(function () use (&$confirmado) {
                $confirmado = true;
            });
            $canal->set_nack_handler(function () use (&$confirmado) {
                $confirmado = false;
            });
            $canal->set_return_listener(function () use (&$devuelto) {
                $devuelto = true;
            });

            $amqp = new AMQPMessage($evento->aJson(), [
                'content_type' => 'application/json',
                'content_encoding' => 'utf-8',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => "evento-{$evento->id}",
                'correlation_id' => $evento->correlationId,
                'type' => $evento->tipo,
                // Prioridad del evento → prioridad AMQP: con mensajes acumulados, se entregan primero los de alta.
                'priority' => config("enrutamiento.prioridades.{$evento->prioridad}", 0),
                'timestamp' => time(),
                'app_id' => 'api-tienda',
            ]);

            // mandatory = true: si ninguna cola está enlazada a esa routing key, el broker lo devuelve.
            $canal->basic_publish($amqp, config('enrutamiento.exchange'), $destino->routingKey(), true);
            $canal->wait_for_pending_acks_returns(config('enrutamiento.rabbitmq.timeout'));
        } catch (Throwable $e) {
            throw new BrokerNoDisponible('El broker no confirmó el mensaje: '.$e->getMessage(), previous: $e);
        } finally {
            try {
                $canal?->close();
                $conexion->close();
            } catch (Throwable) {
                // La conexión ya estaba cerrada.
            }
        }

        if ($devuelto) {
            throw new BrokerNoDisponible("El broker devolvió el mensaje: ninguna cola está enlazada a la routing key \"{$destino->routingKey()}\".");
        }

        if ($confirmado !== true) {
            throw new BrokerNoDisponible('El broker rechazó el mensaje (nack) o no respondió a tiempo.');
        }
    }
}
