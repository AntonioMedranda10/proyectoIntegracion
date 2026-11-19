<?php

namespace App\Mensajeria\RabbitMq;

use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\MensajeNotificacion;
use App\Mensajeria\PublicadorMensajes;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Productor real: publica el mensaje en RabbitMQ y espera la CONFIRMACIÓN del
 * broker (publisher confirms). Solo si el broker la da, la API responde 202.
 */
final class PublicadorRabbitMq implements PublicadorMensajes
{
    public function publicar(MensajeNotificacion $mensaje): void
    {
        $conexion = ConexionRabbitMq::abrir('api-tienda (productor)');
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

            $amqp = new AMQPMessage($mensaje->aJson(), [
                'content_type' => 'application/json',
                'content_encoding' => 'utf-8',
                // Persistente: el mensaje se guarda en disco y sobrevive a un reinicio del broker.
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                // Los mismos identificadores van también como propiedades AMQP (visibles en el panel).
                'message_id' => $mensaje->messageId,
                'correlation_id' => $mensaje->correlationId,
                'type' => $mensaje->tipo,
                'timestamp' => time(),
                'app_id' => 'api-tienda',
            ]);

            // mandatory = true: si ninguna cola recibe el mensaje, el broker lo devuelve en lugar de perderlo.
            $canal->basic_publish($amqp, config('mensajeria.exchange'), config('mensajeria.routing_key'), true);
            $canal->wait_for_pending_acks_returns(config('mensajeria.rabbitmq.timeout'));
        } catch (Throwable $e) {
            throw new BrokerNoDisponible('El broker no confirmó el mensaje: '.$e->getMessage(), previous: $e);
        } finally {
            try {
                $canal?->close();
                $conexion->close();
            } catch (Throwable) {
                // La conexión ya estaba cerrada: no hay nada más que liberar.
            }
        }

        if ($devuelto) {
            throw new BrokerNoDisponible('El broker devolvió el mensaje: ninguna cola está enlazada al exchange.');
        }

        if ($confirmado !== true) {
            throw new BrokerNoDisponible('El broker rechazó el mensaje (nack) o no respondió a tiempo.');
        }
    }
}
