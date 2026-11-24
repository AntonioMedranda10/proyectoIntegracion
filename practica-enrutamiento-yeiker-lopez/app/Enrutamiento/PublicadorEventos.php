<?php

namespace App\Enrutamiento;

use App\Enrutamiento\Excepciones\BrokerNoDisponible;

/**
 * Transporte: entrega el evento en la cola del destino elegido por el enrutador.
 * En la aplicación se usa RabbitMQ; en las pruebas automáticas, una versión en memoria.
 */
interface PublicadorEventos
{
    /** @throws BrokerNoDisponible si el broker no confirma la recepción */
    public function publicar(Evento $evento, Destino $destino): void;
}
