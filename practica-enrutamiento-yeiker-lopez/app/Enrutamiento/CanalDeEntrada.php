<?php

namespace App\Enrutamiento;

use App\Enrutamiento\Excepciones\BrokerNoDisponible;
use App\Models\EventoRecibido;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recorrido de un evento ya VALIDADO (lo valida EventoRequest):
 *
 *   filtro (Message Filter) → enrutador (Content-Based Router) → transporte (RabbitMQ)
 *
 * Cada decisión queda en eventos_recibidos y en storage/logs/enrutamiento.log
 * con el mismo correlation_id.
 */
final class CanalDeEntrada
{
    public function __construct(
        private readonly FiltroDeMensajes $filtro,
        private readonly EnrutadorPorContenido $enrutador,
        private readonly PublicadorEventos $publicador,
    ) {}

    public function recibir(Evento $evento): ResultadoEntrada
    {
        $log = Log::channel('enrutamiento');
        $contexto = ['evento_id' => $evento->id, 'tipo' => $evento->tipo, 'prioridad' => $evento->prioridad];
        $log->info('validacion: evento aceptado por el contrato', $contexto);

        // 1. Message Filter: si no cumple, NO sigue hacia ningún consumidor.
        $filtro = $this->filtro->evaluar($evento);
        if (! $filtro->aceptado) {
            $registro = $this->registrar($evento, EventoRecibido::FILTRADO, $filtro->regla, motivo: $filtro->motivo);
            $log->notice('filtro: rechazado por regla de negocio; no llega a ningún consumidor', $contexto + [
                'regla' => $filtro->regla,
                'motivo' => $filtro->motivo,
            ]);

            return ResultadoEntrada::filtrado($evento, $filtro, $registro);
        }
        $log->info('filtro: aceptado', $contexto);

        // 2. Content-Based Router: elige UN destino según el tipo.
        $decision = $this->enrutador->decidir($evento);
        $registro = $this->registrar(
            $evento,
            $decision->esRevision() ? EventoRecibido::NO_ENRUTABLE : EventoRecibido::ENRUTADO,
            $decision->regla,
            destino: $decision->destino,
            estadoConsumo: $decision->esRevision() ? EventoRecibido::EN_REVISION : EventoRecibido::EN_COLA,
            motivo: $decision->esRevision() ? "Tipo «{$evento->tipo}» no reconocido por el enrutador." : null,
        );
        $log->info("enrutador: {$decision->regla} → {$decision->destino->value}", $contexto + ['cola' => $decision->destino->cola()]);

        // 3. Transporte: publicar en el exchange con la routing key del destino.
        try {
            $this->publicador->publicar($evento, $decision->destino);
        } catch (BrokerNoDisponible $e) {
            $registro->update([
                'decision' => EventoRecibido::ERROR_TRANSPORTE,
                'estado_consumo' => null,
                'motivo' => Str::limit($e->getMessage(), 250),
            ]);
            $log->error('transporte: el broker no confirmó el mensaje (fallo técnico, NO rechazo de negocio)', $contexto + [
                'destino' => $decision->destino->value,
                'motivo' => $e->getMessage(),
            ]);

            return ResultadoEntrada::errorTransporte($evento, $decision, $e->getMessage(), $registro);
        }

        $log->info(
            $decision->esRevision() ? 'transporte: publicado en la cola de revisión' : 'transporte: publicado en la cola del consumidor',
            $contexto + ['cola' => $decision->destino->cola()],
        );

        return ResultadoEntrada::enrutado($evento, $decision, $registro);
    }

    private function registrar(
        Evento $evento,
        string $decision,
        string $regla,
        ?Destino $destino = null,
        ?string $estadoConsumo = null,
        ?string $motivo = null,
    ): EventoRecibido {
        return EventoRecibido::create([
            'evento_id' => $evento->id,
            'correlation_id' => $evento->correlationId,
            'tipo' => $evento->tipo,
            'prioridad' => $evento->prioridad,
            'decision' => $decision,
            'regla' => $regla,
            'destino' => $destino?->value,
            'cola' => $destino?->cola(),
            'motivo' => $motivo ? Str::limit($motivo, 250) : null,
            'estado_consumo' => $estadoConsumo,
            'recibido_en' => now(),
        ]);
    }
}
