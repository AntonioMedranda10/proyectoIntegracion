<?php

namespace App\Enrutamiento\Excepciones;

use RuntimeException;

/**
 * Error PERMANENTE dentro de un consumidor (por ejemplo, el pedido no existe).
 * Reintentar no lo arregla: el mensaje va a la cola de fallidos.
 */
class EventoNoProcesable extends RuntimeException {}
