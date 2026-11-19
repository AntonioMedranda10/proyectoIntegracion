<?php

namespace App\Mensajeria\Excepciones;

use RuntimeException;

/**
 * No se pudo conectar con RabbitMQ o el broker no confirmó que guardó el
 * mensaje. El productor responde 503 y NO afirma que la solicitud quedó en cola.
 */
class BrokerNoDisponible extends RuntimeException {}
