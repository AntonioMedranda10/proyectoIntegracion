<?php

/*
 * Práctica 3.2 – Patrones de enrutamiento y distribución lógica.
 *
 * Broker real: el mismo RabbitMQ de la práctica 3.1. Los valores secretos
 * (usuario y clave) viven en .env; aquí solo están los nombres de las variables.
 */
return [

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'vhost' => env('RABBITMQ_VHOST', 'tienda'),
        'usuario' => env('RABBITMQ_USER'),
        'clave' => env('RABBITMQ_PASSWORD'),
        'timeout' => (float) env('RABBITMQ_TIMEOUT', 3),
    ],

    // Exchange "direct": el enrutador de Laravel decide la routing key y el broker
    // entrega el mensaje a la única cola enlazada con esa clave.
    'exchange' => env('ENRUTAMIENTO_EXCHANGE', 'tienda.eventos'),

    // routing key (= destino) → cola
    'colas' => [
        'facturacion' => 'tienda.facturacion',
        'logistica' => 'tienda.logistica',
        'inventario' => 'tienda.inventario',
        'no_enrutables' => 'tienda.no_enrutables',
    ],

    // Fallos de PROCESAMIENTO en un consumidor (no confundir con el filtrado de negocio).
    'exchange_fallidos' => env('ENRUTAMIENTO_EXCHANGE', 'tienda.eventos').'.dlx',
    'cola_fallidos' => 'tienda.eventos.fallidos',

    // Prioridad del evento → prioridad AMQP (0 a 9). Con mensajes acumulados, la cola entrega primero los de alta.
    'prioridades' => ['alta' => 9, 'normal' => 5, 'baja' => 1],

];
