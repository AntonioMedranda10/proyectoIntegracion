<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\EventoNoProcesable;
use App\Enrutamiento\Excepciones\MensajeInvalido;
use App\Models\EventoRecibido;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lógica común de los consumidores: valida el mensaje, comprueba que le
 * corresponde a ESTE consumidor, lo procesa, actualiza el recorrido y decide
 * ack o nack. No depende de RabbitMQ, por eso se puede probar sin broker.
 */
final class ManejadorDeConsumo
{
    public function manejar(Consumidor $consumidor, string $cuerpo, bool $reentregado): ResultadoConsumo
    {
        $log = Log::channel('enrutamiento');
        $nombre = $consumidor->destino()->value;

        try {
            $evento = Evento::desdeJson($cuerpo);
        } catch (MensajeInvalido $e) {
            $log->error("consumidor {$nombre}: mensaje fuera de contrato; nack a fallidos", ['motivo' => $e->getMessage()]);

            return new ResultadoConsumo(Decision::EnviarAFallidos, $e->getMessage());
        }

        Context::add('correlation_id', $evento->correlationId);

        try {
            return $this->procesar($consumidor, $evento, $reentregado, $nombre);
        } finally {
            Context::forget('correlation_id');
        }
    }

    private function procesar(Consumidor $consumidor, Evento $evento, bool $reentregado, string $nombre): ResultadoConsumo
    {
        $log = Log::channel('enrutamiento');
        $contexto = ['evento_id' => $evento->id, 'tipo' => $evento->tipo, 'prioridad' => $evento->prioridad];
        $registro = EventoRecibido::delConsumidor($evento->id, $evento->correlationId);

        // Defensa: este consumidor solo procesa sus tipos. Si llegara otro, sería un error de enrutamiento.
        if (! in_array($evento->tipo, $consumidor->tipos(), true)) {
            $motivo = "El evento de tipo «{$evento->tipo}» no le corresponde a {$nombre}; no se procesa.";
            $registro?->marcarFallido($nombre, $motivo);
            $log->error("consumidor {$nombre}: mensaje que no le corresponde; nack a fallidos", $contexto);

            return new ResultadoConsumo(Decision::EnviarAFallidos, $motivo, $evento);
        }

        $log->info("consumidor {$nombre}: evento recibido", $contexto + ['reentregado' => $reentregado]);

        try {
            $resultado = $consumidor->procesar($evento);
        } catch (EventoNoProcesable $e) {
            $registro?->marcarFallido($nombre, $e->getMessage());
            $log->warning("consumidor {$nombre}: no se pudo procesar (error permanente); nack a fallidos", $contexto + ['motivo' => $e->getMessage()]);

            return new ResultadoConsumo(Decision::EnviarAFallidos, $e->getMessage(), $evento);
        } catch (Throwable $e) {
            if (! $reentregado) {
                $log->warning("consumidor {$nombre}: error inesperado; nack con reencolado (un reintento)", $contexto + ['motivo' => $e->getMessage()]);

                return new ResultadoConsumo(Decision::Reencolar, $e->getMessage(), $evento);
            }
            $registro?->marcarFallido($nombre, 'Falló también en el reintento: '.$e->getMessage());
            $log->error("consumidor {$nombre}: falló el reintento; nack a fallidos", $contexto + ['motivo' => $e->getMessage()]);

            return new ResultadoConsumo(Decision::EnviarAFallidos, $e->getMessage(), $evento);
        }

        $registro?->marcarProcesado($nombre, $resultado, now());
        $log->info("consumidor {$nombre}: procesado; ack", $contexto + ['resultado' => $resultado]);

        return new ResultadoConsumo(Decision::Confirmar, $resultado, $evento);
    }
}
