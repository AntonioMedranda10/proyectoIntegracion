<?php

namespace App\Mensajeria\Excepciones;

use RuntimeException;

/**
 * El mensaje que llegó de la cola no cumple el contrato (JSON roto o campos
 * faltantes). Reintentarlo no lo arregla: va directo a la cola de fallidas.
 */
class MensajeInvalido extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?string $correlationId = null)
    {
        parent::__construct($mensaje);
    }
}
