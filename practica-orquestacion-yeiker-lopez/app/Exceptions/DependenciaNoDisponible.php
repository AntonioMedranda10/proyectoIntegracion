<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un servicio del que depende el orquestador falló: sin conexión, sin respuesta a
 * tiempo, código HTTP no exitoso o contenido que no tiene el formato esperado.
 */
class DependenciaNoDisponible extends RuntimeException
{
    public function __construct(
        public readonly string $servicio,
        public readonly string $motivo,
        public readonly string $url,
        public readonly int $milisegundos,
    ) {
        parent::__construct("El servicio de {$servicio} falló: {$motivo}");
    }
}
