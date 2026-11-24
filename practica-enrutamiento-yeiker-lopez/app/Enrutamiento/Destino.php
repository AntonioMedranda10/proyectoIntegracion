<?php

namespace App\Enrutamiento;

/**
 * Destinos posibles del Content-Based Router.
 * El valor es la routing key con la que se publica en el exchange tienda.eventos.
 */
enum Destino: string
{
    case Facturacion = 'facturacion';
    case Logistica = 'logistica';
    case Inventario = 'inventario';
    case NoEnrutables = 'no_enrutables';

    /** Cola enlazada a este destino. */
    public function cola(): string
    {
        return config("enrutamiento.colas.{$this->value}");
    }

    public function routingKey(): string
    {
        return $this->value;
    }

    /** Qué hace el proceso que recibe los mensajes de este destino. */
    public function consumidor(): string
    {
        return match ($this) {
            self::Facturacion => 'Facturación: emite el comprobante del pago',
            self::Logistica => 'Logística: registra la guía de envío',
            self::Inventario => 'Inventario: devuelve al stock las unidades del pedido cancelado',
            self::NoEnrutables => 'Revisión manual: el mensaje espera en la cola, sin consumidor automático',
        };
    }

    /** Destinos que tienen un consumidor automático. */
    public static function consumibles(): array
    {
        return [self::Facturacion, self::Logistica, self::Inventario];
    }
}
