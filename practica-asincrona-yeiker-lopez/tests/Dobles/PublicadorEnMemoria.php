<?php

namespace Tests\Dobles;

use App\Mensajeria\Excepciones\BrokerNoDisponible;
use App\Mensajeria\MensajeNotificacion;
use App\Mensajeria\PublicadorMensajes;

/**
 * Productor de prueba: guarda los mensajes en memoria en lugar de enviarlos a
 * RabbitMQ. Con $brokerCaido = true simula que el broker no responde.
 */
final class PublicadorEnMemoria implements PublicadorMensajes
{
    /** @var list<MensajeNotificacion> */
    public array $publicados = [];

    public bool $brokerCaido = false;

    public function publicar(MensajeNotificacion $mensaje): void
    {
        if ($this->brokerCaido) {
            throw new BrokerNoDisponible('No se pudo conectar con RabbitMQ en 127.0.0.1:5672 (simulado en la prueba).');
        }

        $this->publicados[] = $mensaje;
    }
}
