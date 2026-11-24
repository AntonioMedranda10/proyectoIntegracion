<?php

namespace App\Console\Commands;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\RabbitMq\ConexionRabbitMq;
use Illuminate\Console\Command;

/**
 * Crea la topología en RabbitMQ (si no existe) y muestra cada destino con su
 * routing key, su cola y cuántos mensajes y consumidores tiene.
 *
 *   php artisan mensajeria:preparar
 */
class PrepararMensajeria extends Command
{
    protected $signature = 'mensajeria:preparar';

    protected $description = 'Crea el exchange y las colas de enrutamiento en RabbitMQ y muestra su estado';

    public function handle(): int
    {
        $c = config('enrutamiento.rabbitmq');

        // Parámetros de conexión SIN secretos.
        $this->table(['Parámetro', 'Valor'], [
            ['Host y puerto AMQP', "{$c['host']}:{$c['port']}"],
            ['Virtual host', $c['vhost']],
            ['Usuario y clave', 'definidos en .env (no se muestran)'],
            ['Exchange', config('enrutamiento.exchange').' (direct, durable)'],
        ]);

        try {
            $conexion = ConexionRabbitMq::abrir('mensajeria:preparar');
        } catch (BrokerNoDisponible $e) {
            $this->error($e->getMessage());
            $this->line('¿Está iniciado el broker? Ejecute: docker compose up -d y espere a que diga "healthy".');

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        ConexionRabbitMq::declararTopologia($canal);

        $filas = [];
        foreach (Destino::cases() as $destino) {
            [, $mensajes, $consumidores] = $canal->queue_declare($destino->cola(), true);
            $filas[] = [$destino->routingKey(), $destino->cola(), $mensajes, $consumidores];
        }
        [, $mensajes, $consumidores] = $canal->queue_declare(config('enrutamiento.cola_fallidos'), true);
        $filas[] = ['(fallos de procesamiento)', config('enrutamiento.cola_fallidos'), $mensajes, $consumidores];

        $canal->close();
        $conexion->close();

        $this->info('Topología lista. Destinos del enrutador:');
        $this->table(['Routing key', 'Cola', 'Mensajes en espera', 'Consumidores'], $filas);

        return self::SUCCESS;
    }
}
