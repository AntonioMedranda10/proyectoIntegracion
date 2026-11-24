<?php

namespace App\Enrutamiento\Consumidores;

use App\Enrutamiento\Destino;
use App\Enrutamiento\Evento;
use App\Enrutamiento\Excepciones\EventoNoProcesable;
use App\Models\Envio;
use App\Models\Pedido;
use Illuminate\Support\Str;

/** Logística: por cada pedido despachado registra UNA guía de envío. */
final class ConsumidorLogistica implements Consumidor
{
    public function destino(): Destino
    {
        return Destino::Logistica;
    }

    public function tipos(): array
    {
        return ['pedido_despachado'];
    }

    public function procesar(Evento $evento): string
    {
        $pedidoId = (int) $evento->dato('pedido_id');
        $pedido = Pedido::find($pedidoId) ?? throw new EventoNoProcesable("El pedido {$pedidoId} no existe.");

        if ($previo = Envio::firstWhere('pedido_id', $pedido->id)) {
            return "El pedido {$pedido->id} ya tenía la guía {$previo->guia}; no se registra otra.";
        }

        $guia = sprintf('GUIA-%d-%s', $pedido->id, Str::upper(Str::random(4)));
        $transportista = (string) $evento->dato('transportista');
        Envio::create([
            'pedido_id' => $pedido->id,
            'guia' => $guia,
            'transportista' => $transportista,
            'evento_id' => $evento->id,
            'registrado_en' => now(),
        ]);

        return "Guía {$guia} registrada con {$transportista} (pedido {$pedido->id})";
    }
}
