<?php

namespace App\Console\Commands;

use App\Mensajeria\Decision;
use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\ManejadorMensajes;
use App\Mensajeria\RabbitMq\ConexionRabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * CONSUMIDOR independiente: un proceso aparte de la API que lee la cola
 * "notificaciones.pedidos" y procesa los mensajes uno por uno.
 *
 *   php artisan notificaciones:consumir
 *
 * Ack manual: el mensaje se confirma SOLO después de procesarlo. Si el proceso
 * se cierra (Ctrl+C) antes del ack, RabbitMQ devuelve el mensaje a la cola.
 */
class ConsumirNotificaciones extends Command
{
    protected $signature = 'notificaciones:consumir
                            {--max=0 : Detenerse después de N mensajes (0 = seguir escuchando)}';

    protected $description = 'Consumidor: procesa las notificaciones de pedidos que llegan a la cola de RabbitMQ';

    public function handle(ManejadorMensajes $manejador): int
    {
        $cola = config('mensajeria.cola');

        try {
            $conexion = ConexionRabbitMq::abrir('consumidor-notificaciones');
        } catch (BrokerNoDisponible $e) {
            $this->error($e->getMessage());
            $this->line('¿Está iniciado el broker? Ejecute: docker compose up -d');

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        ConexionRabbitMq::declararTopologia($canal);

        // prefetch = 1: RabbitMQ entrega un mensaje a la vez y no manda el siguiente hasta recibir el ack.
        $canal->basic_qos(0, 1, false);

        $maximo = (int) $this->option('max');
        $atendidos = 0;

        $canal->basic_consume(
            queue: $cola,
            no_ack: false, // ack MANUAL
            callback: function (AMQPMessage $amqp) use ($manejador, $canal, $maximo, &$atendidos) {
                $decision = $manejador->manejar($amqp->getBody(), $amqp->isRedelivered());

                match ($decision) {
                    Decision::Confirmar => $amqp->ack(),
                    Decision::Reencolar => $amqp->nack(requeue: true),
                    Decision::EnviarAFallidas => $amqp->nack(requeue: false),
                };

                $this->line(sprintf(
                    '[%s] correlation_id=%s  message_id=%s  → %s',
                    now()->format('H:i:s.v'),
                    $amqp->has('correlation_id') ? $amqp->get('correlation_id') : '?',
                    $amqp->has('message_id') ? $amqp->get('message_id') : '?',
                    $decision->value,
                ));

                if ($maximo > 0 && ++$atendidos >= $maximo) {
                    $canal->stopConsume();
                }
            },
        );

        Log::channel('mensajeria')->info('consumidor: iniciado; esperando mensajes', ['cola' => $cola, 'prefetch' => 1]);
        $this->info(sprintf(
            'Consumidor escuchando la cola "%s" (vhost "%s"). Presione Ctrl+C para detenerlo.',
            $cola,
            config('mensajeria.rabbitmq.vhost'),
        ));

        try {
            $canal->consume();
        } finally {
            $canal->close();
            $conexion->close();
        }

        Log::channel('mensajeria')->info('consumidor: detenido', ['mensajes_atendidos' => $atendidos]);

        return self::SUCCESS;
    }
}
