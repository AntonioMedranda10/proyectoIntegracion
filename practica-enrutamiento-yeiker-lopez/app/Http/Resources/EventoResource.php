<?php

namespace App\Http\Resources;

use App\Support\Fecha;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recorrido de un evento, tal como lo ve el cliente.
 *
 * @mixin \App\Models\EventoRecibido
 */
class EventoResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'eventoId' => $this->evento_id,
            'tipo' => $this->tipo,
            'prioridad' => $this->prioridad,
            'decision' => $this->decision,
            'regla' => $this->regla,
            'destino' => $this->destino,
            'cola' => $this->cola,
            'motivo' => $this->motivo,
            'estadoConsumo' => $this->estado_consumo,
            'consumidor' => $this->consumidor,
            'resultado' => $this->resultado,
            'correlationId' => $this->correlation_id,
            'recibidoEn' => Fecha::iso($this->recibido_en),
            'procesadoEn' => Fecha::iso($this->procesado_en),
        ];
    }
}
