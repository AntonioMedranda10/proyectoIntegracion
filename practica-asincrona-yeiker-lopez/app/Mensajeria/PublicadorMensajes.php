<?php

namespace App\Mensajeria;

use App\Mensajeria\Excepciones\BrokerNoDisponible;

/**
 * Productor: entrega un mensaje al broker.
 * En la aplicación se usa RabbitMQ; en las pruebas automáticas, una versión en memoria.
 */
interface PublicadorMensajes
{
    /**
     * Vuelve solo cuando el broker confirmó que guardó el mensaje.
     *
     * @throws BrokerNoDisponible
     */
    public function publicar(MensajeNotificacion $mensaje): void;
}
