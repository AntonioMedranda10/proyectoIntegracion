<?php
// Servicio de USUARIOS del laboratorio: un proceso PHP independiente del orquestador.
// Iniciar con:  php -S 127.0.0.1:8001 servicios/usuarios.php
//
// GET /usuarios/7 → {"id":7,"nombre":"Ana"} · cualquier otro → 404

header('Content-Type: application/json; charset=utf-8');

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$recibido = $_SERVER['HTTP_X_CORRELATION_ID'] ?? '';
$correlationId = preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $recibido) ? $recibido : null;

$usuarios = [
    7 => ['id' => 7, 'nombre' => 'Ana'],
];

if (preg_match('#^/usuarios/(\d+)$#', $ruta, $partes) && isset($usuarios[(int) $partes[1]])) {
    [$codigo, $cuerpo] = [200, $usuarios[(int) $partes[1]]];
} else {
    [$codigo, $cuerpo] = [404, ['error' => 'Usuario no encontrado']];
}

http_response_code($codigo);
if ($correlationId !== null) {
    header("X-Correlation-ID: {$correlationId}");
}
// Aparece en esta terminal: permite seguir la misma operación en cada servicio.
error_log("[usuarios] {$_SERVER['REQUEST_METHOD']} {$ruta} -> {$codigo} | correlation_id=".($correlationId ?? '(ninguno)'));

echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
