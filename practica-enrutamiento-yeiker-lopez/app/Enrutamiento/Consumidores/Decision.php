<?php

namespace App\Enrutamiento\Consumidores;

/** Qué le responde el consumidor a RabbitMQ después de procesar un mensaje. */
enum Decision: string
{
    /** ack: procesado; RabbitMQ lo borra de la cola. */
    case Confirmar = 'ack';

    /** nack + requeue: error inesperado; vuelve a la cola para un segundo intento. */
    case Reencolar = 'nack (reencolar)';

    /** nack sin requeue: error permanente; pasa a la cola de fallidos. */
    case EnviarAFallidos = 'nack (a fallidos)';
}
