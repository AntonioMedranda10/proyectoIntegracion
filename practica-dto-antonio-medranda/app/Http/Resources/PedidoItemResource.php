<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PedidoItem */
class PedidoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'producto_id' => $this->producto_id,
            'nombre' => $this->nombre_producto,
            'cantidad' => $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
