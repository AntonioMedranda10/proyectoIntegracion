<?php

namespace App\Console\Commands;

use App\Enrutamiento\Consumidores\ConsumidorFacturacion;
use App\Enrutamiento\Consumidores\ConsumidorInventario;
use App\Enrutamiento\Consumidores\ConsumidorLogistica;
use App\Enrutamiento\Consumidores\Decision;
use App\Enrutamiento\Consumidores\ManejadorDeConsumo;
use App\Enrutamiento\Destino;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\RabbitMq\ConexionRabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * Un CONSUMIDOR por proceso, cada uno escuchando SOLO la cola de su destino:
 *
 *   php artisan eventos:consumir facturacion
 *   php artisan eventos:consumir logistica
 *   php artisan eventos:consumir inventario
 */
class ConsumirEventos extends Command
{
    protected $signature = 'eventos:consumir
                            {destino : facturacion, logistica o inventario}
                            {--max=0 : Detenerse después de N mensajes (0 = seguir escuchando)}';

    protected $description = 'Consumidor de un destino: procesa solo los eventos que el enrutador envió a su cola';

    public function handle(ManejadorDeConsumo $manejador): int
    {
        $destino = Destino::tryFrom((string) $this->argument('destino'));
        if (! in_array($destino, Destino::consumibles(), true)) {
            $this->error('Destino no válido. Use: facturacion, logistica o inventario.');

            return self::INVALID;
        }

        $consumidor = app(match ($destino) {
            Destino::Facturacion => ConsumidorFacturacion::class,
            Destino::Logistica => ConsumidorLogistica::class,
            Destino::Inventario => ConsumidorInventario::class,
        });

        try {
            $conexion = ConexionRabbitMq::abrir("consumidor-{$destino->value}");
        } catch (BrokerNoDisponible $e) {
            $this->error($e->getMessage());
            $this->line('¿Está iniciado el broker? Ejecute: docker compose up -d');

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        ConexionRabbitMq::declararTopologia($canal);
        $canal->basic_qos(0, 1, false); // un mensaje a la vez; el siguiente llega después del ack

        $maximo = (int) $this->option('max');
        $atendidos = 0;

        $canal->basic_consume(
            queue: $destino->cola(),
            no_ack: false,
            callback: function (AMQPMessage $amqp) use ($manejador, $consumidor, $canal, $maximo, &$atendidos) {
                $resultado = $manejador->manejar($consumidor, $amqp->getBody(), $amqp->isRedelivered());

                match ($resultado->decision) {
                    Decision::Confirmar => $amqp->ack(),
                    Decision::Reencolar => $amqp->nack(requeue: true),
                    Decision::EnviarAFallidos => $amqp->nack(requeue: false),
                };

                $evento = $resultado->evento;
                $this->line(sprintf(
                    '[%s] evento %s · %s · prioridad %s · correlation=%s → %s · %s',
                    now()->format('H:i:s.v'),
                    $evento?->id ?? '?',
                    $evento?->tipo ?? '?',
                    $evento?->prioridad ?? '?',
                    $evento?->correlationId ?? '?',
                    $resultado->decision->value,
                    $resultado->detalle,
                ));

                if ($maximo > 0 && ++$atendidos >= $maximo) {
                    $canal->stopConsume();
                }
            },
        );

        Log::channel('enrutamiento')->info("consumidor {$destino->value}: iniciado", ['cola' => $destino->cola()]);
        $this->info(sprintf('Consumidor de %s escuchando la cola "%s". Presione Ctrl+C para detenerlo.', $destino->value, $destino->cola()));
        $this->line($destino->consumidor());

        try {
            $canal->consume();
        } catch (AMQPRuntimeException $e) {
            // Fallo de TRANSPORTE (el broker se detuvo o cortó la conexión): los mensajes sin ack vuelven a la cola.
            Log::channel('enrutamiento')->error("consumidor {$destino->value}: se perdió la conexión con el broker", ['motivo' => $e->getMessage()]);
            $this->error('Se perdió la conexión con RabbitMQ. Cuando el broker vuelva (docker compose up -d), inicie de nuevo este consumidor.');

            return self::FAILURE;
        } finally {
            try {
                $canal->close();
                $conexion->close();
            } catch (Throwable) {
                // La conexión ya estaba cerrada.
            }
        }

        return self::SUCCESS;
    }
}
