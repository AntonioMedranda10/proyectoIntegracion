<?php

// Dependencias del orquestador. Las direcciones salen del .env para poder
// cambiar puertos sin tocar el código (y funcionan con la configuración cacheada).
return [
    'servicios' => [
        'usuarios' => env('ORQ_USUARIOS_URL', 'http://127.0.0.1:8001'),
        'estados' => env('ORQ_ESTADOS_URL', 'http://127.0.0.1:8002'),
    ],

    // Segundos que se espera a cada servicio (conexión y respuesta).
    'timeout' => (int) env('ORQ_TIMEOUT', 3),
];
