<?php

namespace App\Enrutamiento\Excepciones;

use RuntimeException;

/**
 * FALLO DE TRANSPORTE: no se pudo conectar con RabbitMQ o el broker no
 * confirmó el mensaje. No es un rechazo de negocio (eso lo decide el filtro).
 */
class BrokerNoDisponible extends RuntimeException {}
