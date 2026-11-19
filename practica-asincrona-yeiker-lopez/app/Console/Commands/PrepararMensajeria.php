<?php

namespace App\Console\Commands;

use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\RabbitMq\ConexionRabbitMq;
use Illuminate\Console\Command;

/**
 * Crea la topología en RabbitMQ (si no existe) y muestra cuántos mensajes
 * esperan en cada cola. Se puede ejecutar las veces que se quiera.
 *
 *   php artisan mensajeria:preparar
 */
class PrepararMensajeria extends Command
{
    protected $signature = 'mensajeria:preparar';

    protected $description = 'Crea el exchange y las colas de notificaciones en RabbitMQ y muestra su estado';

    public function handle(): int
    {
        $c = config('mensajeria.rabbitmq');
        $m = config('mensajeria');

        // Parámetros de conexión SIN secretos: la clave nunca se imprime.
        $this->table(['Parámetro', 'Valor'], [
            ['Host', $c['host']],
            ['Puerto AMQP', $c['port']],
            ['Virtual host', $c['vhost']],
            ['Usuario y clave', 'definidos en .env (no se muestran)'],
            ['Exchange', $m['exchange'].' (direct, durable)'],
            ['Routing key', $m['routing_key']],
        ]);

        try {
            $conexion = ConexionRabbitMq::abrir('mensajeria:preparar');
        } catch (BrokerNoDisponible $e) {
            $this->error($e->getMessage());
            $this->line('¿Está iniciado el broker? Ejecute: docker compose up -d');

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        ConexionRabbitMq::declararTopologia($canal);

        $filas = [];
        foreach ([$m['cola'], $m['cola_fallidas']] as $nombre) {
            // passive = true: solo consulta la cola (ya existe), no la modifica.
            [, $mensajes, $consumidores] = $canal->queue_declare($nombre, true);
            $filas[] = [$nombre, $mensajes, $consumidores];
        }

        $canal->close();
        $conexion->close();

        $this->info('Topología lista. Estado de las colas:');
        $this->table(['Cola', 'Mensajes en espera', 'Consumidores conectados'], $filas);

        return self::SUCCESS;
    }
}
