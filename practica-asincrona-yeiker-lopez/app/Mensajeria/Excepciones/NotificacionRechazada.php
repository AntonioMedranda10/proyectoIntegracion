<?php

namespace App\Mensajeria\Excepciones;

use RuntimeException;

/**
 * Error PERMANENTE al procesar (por ejemplo, el cliente no tiene correo).
 * Reintentar daría el mismo resultado, así que el mensaje no se reencola.
 */
class NotificacionRechazada extends RuntimeException {}
