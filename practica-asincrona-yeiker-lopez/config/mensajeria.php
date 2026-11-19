<?php

/*
 * Práctica 3.1 – Infraestructura de comunicación asíncrona.
 *
 * Parámetros del broker (RabbitMQ) y de la topología. Los valores reales
 * viven en .env: aquí solo están los nombres de las variables y valores por
 * defecto que no son secretos.
 */
return [

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'vhost' => env('RABBITMQ_VHOST', 'tienda'),
        'usuario' => env('RABBITMQ_USER'),
        'clave' => env('RABBITMQ_PASSWORD'),
        // Segundos máximos para conectar y para esperar la confirmación del broker.
        'timeout' => (float) env('RABBITMQ_TIMEOUT', 3),
    ],

    // Exchange "direct" que recibe los mensajes y los enruta a la cola.
    'exchange' => env('MENSAJERIA_EXCHANGE', 'tienda.notificaciones'),
    'routing_key' => 'pedido.notificacion',

    // Cola principal que lee el consumidor.
    'cola' => env('MENSAJERIA_COLA', 'notificaciones.pedidos'),

    // Dead-letter: a dónde van los mensajes rechazados (nack sin reencolar).
    'exchange_fallidas' => env('MENSAJERIA_EXCHANGE', 'tienda.notificaciones').'.dlx',
    'cola_fallidas' => env('MENSAJERIA_COLA_FALLIDAS', 'notificaciones.pedidos.fallidas'),

    // Proveedor de correo SIMULADO: cuánto tarda en "enviar" cada notificación.
    'tiempo_envio_ms' => (int) env('NOTIF_TIEMPO_ENVIO_MS', 2000),

];
