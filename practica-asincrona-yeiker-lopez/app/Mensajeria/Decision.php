<?php

namespace App\Mensajeria;

/**
 * Qué le responde el consumidor a RabbitMQ después de procesar un mensaje.
 */
enum Decision: string
{
    /** ack: procesado; RabbitMQ lo borra de la cola. */
    case Confirmar = 'ack';

    /** nack + requeue: error transitorio; vuelve a la cola para un segundo intento. */
    case Reencolar = 'nack (reencolar)';

    /** nack sin requeue: error permanente; RabbitMQ lo mueve a la cola de fallidas. */
    case EnviarAFallidas = 'nack (a fallidas)';
}
