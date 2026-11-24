<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\EventoNoProcesable;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

/** Inventario: por cada pedido cancelado devuelve al stock sus unidades (una sola vez). */
final class ConsumidorInventario implements Consumidor
{
    public const MOTIVO = 'cancelacion';

    public function destino(): Destino
    {
        return Destino::Inventario;
    }

    public function tipos(): array
    {
        return ['pedido_cancelado'];
    }

    public function procesar(Evento $evento): string
    {
        $pedidoId = (int) $evento->dato('pedido_id');
        $pedido = Pedido::with('items.producto')->find($pedidoId)
            ?? throw new EventoNoProcesable("El pedido {$pedidoId} no existe.");

        return DB::transaction(function () use ($evento, $pedido) {
            $yaDevuelto = MovimientoInventario::where('pedido_id', $pedido->id)->where('motivo', self::MOTIVO)->exists();
            if ($yaDevuelto) {
                return "El stock del pedido {$pedido->id} ya se había devuelto; no se devuelve otra vez.";
            }

            $detalle = [];
            foreach ($pedido->items as $item) {
                $producto = $item->producto;
                $producto->increment('stock', $item->cantidad);
                MovimientoInventario::create([
                    'producto_id' => $producto->id,
                    'pedido_id' => $pedido->id,
                    'cantidad' => $item->cantidad,
                    'motivo' => self::MOTIVO,
                    'stock_resultante' => $producto->stock,
                    'evento_id' => $evento->id,
                    'registrado_en' => now(),
                ]);
                $detalle[] = "{$producto->nombre} +{$item->cantidad} (queda {$producto->stock})";
            }

            return "Stock devuelto del pedido {$pedido->id}: ".implode(', ', $detalle);
        });
    }
}
