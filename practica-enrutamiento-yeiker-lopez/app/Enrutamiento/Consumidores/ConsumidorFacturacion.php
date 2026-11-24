<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\EventoNoProcesable;
use App\Models\Comprobante;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

/** Facturación: por cada pago aprobado emite UN comprobante del pedido. */
final class ConsumidorFacturacion implements Consumidor
{
    public function destino(): Destino
    {
        return Destino::Facturacion;
    }

    public function tipos(): array
    {
        return ['pago_aprobado'];
    }

    public function procesar(Evento $evento): string
    {
        $pedidoId = (int) $evento->dato('pedido_id');
        $pedido = Pedido::find($pedidoId) ?? throw new EventoNoProcesable("El pedido {$pedidoId} no existe.");

        return DB::transaction(function () use ($evento, $pedido) {
            if ($previo = Comprobante::firstWhere('pedido_id', $pedido->id)) {
                return "El pedido {$pedido->id} ya tenía el comprobante {$previo->numero}; no se emite otro.";
            }

            $numero = sprintf('FAC-%06d', (Comprobante::max('id') ?? 0) + 1);
            $monto = (float) $evento->dato('monto', $pedido->total);
            Comprobante::create([
                'numero' => $numero,
                'pedido_id' => $pedido->id,
                'monto' => $monto,
                'metodo' => $evento->dato('metodo'),
                'evento_id' => $evento->id,
                'emitido_en' => now(),
            ]);

            return sprintf('Comprobante %s emitido por $%s (pedido %d)', $numero, number_format($monto, 2), $pedido->id);
        });
    }
}
