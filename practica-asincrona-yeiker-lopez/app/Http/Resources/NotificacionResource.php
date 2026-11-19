<?php

namespace App\Http\Resources;

use App\Support\Fecha;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de la tabla de estados, tal como la ve el cliente.
 *
 * @mixin \App\Models\NotificacionPedido
 */
class NotificacionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'messageId' => $this->message_id,
            'correlationId' => $this->correlation_id,
            'pedidoId' => $this->pedido_id,
            'evento' => $this->evento,
            'modo' => $this->modo,
            'estado' => $this->estado,
            'intentos' => $this->intentos,
            'destinatario' => $this->destinatario,
            'detalle' => $this->detalle,
            'recibidoEn' => Fecha::iso($this->recibido_en),
            'procesadoEn' => Fecha::iso($this->procesado_en),
            'demoraTotalMs' => $this->duracionMs(),
        ];
    }
}
