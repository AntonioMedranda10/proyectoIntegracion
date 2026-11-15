<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato de RESPUESTA de "Registrar un pedido".
 *
 * SERIALIZACIÓN: convierte el modelo Pedido (objeto PHP) en el JSON acordado.
 * Fija nombres, tipos y formato de cada campo, de modo que el cliente web y el
 * móvil reciben siempre la misma estructura, sin depender de la tabla interna.
 *
 * @mixin \App\Models\Pedido
 */
class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pedido_id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'estado' => $this->estado,
            'metodo_pago' => $this->metodo_pago,
            'direccion_entrega' => $this->direccion_entrega,
            'observacion' => $this->observacion,
            // decimal se guarda como texto ("119.80"); el contrato lo expone como número.
            'total' => (float) $this->total,
            'moneda' => 'USD',
            'cantidad_productos' => $this->items->sum('cantidad'),
            // Fecha en ISO 8601 con zona horaria: sin ambigüedad entre clientes.
            'fecha_registro' => $this->created_at->toIso8601String(),
            'items' => PedidoItemResource::collection($this->items),
        ];
    }
}
