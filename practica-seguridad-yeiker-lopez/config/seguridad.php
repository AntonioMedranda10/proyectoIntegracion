<?php

// Contraseñas de los usuarios de prueba. Solo se leen del .env: nunca se escriben en el código.
return [
    'clave_admin' => env('SEED_ADMIN_PASSWORD'),
    'clave_cliente' => env('SEED_CLIENTE_PASSWORD'),
];
