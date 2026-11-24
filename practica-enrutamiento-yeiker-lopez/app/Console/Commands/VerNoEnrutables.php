<?php

namespace App\Console\Commands;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Enrutamiento\RabbitMq\ConexionRabbitMq;
use Illuminate\Console\Command;

/**
 * Muestra los eventos que esperan revisión en la cola no_enrutables SIN
 * sacarlos de ella: los lee sin confirmar y, al cerrar el canal, RabbitMQ
 * los devuelve a la cola.
 *
 *   php artisan eventos:no-enrutables
 */
class VerNoEnrutables extends Command
{
    protected $signature = 'eventos:no-enrutables';

    protected $description = 'Lista los eventos con tipo no reconocido que esperan revisión manual';

    public function handle(): int
    {
        try {
            $conexion = ConexionRabbitMq::abrir('revision-no-enrutables');
        } catch (BrokerNoDisponible $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $canal = $conexion->channel();
        ConexionRabbitMq::declararTopologia($canal);
        $cola = Destino::NoEnrutables->cola();
        [, $total] = $canal->queue_declare($cola, true);

        $filas = [];
        for ($i = 0; $i < $total; $i++) {
            $mensaje = $canal->basic_get($cola); // sin ack: sigue perteneciendo a la cola
            if (! $mensaje) {
                break;
            }
            $evento = json_decode($mensaje->getBody(), true) ?: [];
            $filas[] = [
                $evento['id'] ?? '?',
                $evento['tipo'] ?? '?',
                $evento['prioridad'] ?? '?',
                $evento['correlation_id'] ?? '?',
                json_encode($evento['datos'] ?? [], JSON_UNESCAPED_UNICODE),
            ];
        }

        // Cerrar sin ack: RabbitMQ devuelve los mensajes a la cola (nada se pierde).
        $canal->close();
        $conexion->close();

        $this->info("Cola \"{$cola}\": {$total} evento(s) esperando revisión manual.");
        if ($filas) {
            $this->table(['id', 'tipo', 'prioridad', 'correlation_id', 'datos'], $filas);
        }

        return self::SUCCESS;
    }
}
