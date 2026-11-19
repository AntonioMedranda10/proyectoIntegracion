<?php
// Servicio de ESTADOS del laboratorio: un proceso PHP independiente del orquestador.
// Iniciar con:  php -S 127.0.0.1:8002 servicios/estados.php
//
// GET /estados/101 → {"solicitud_id":101,"estado":"pendiente"}   (caso de la guía)
// Escenarios para demostrar fallas sin modificar código:
//   102 → responde después de 5 s       (el orquestador corta a los 3 s: timeout)
//   103 → responde HTTP 500             (error interno del servicio)
//   104 → responde 200 con otro formato (contenido inválido)
//   otro → 404

header('Content-Type: application/json; charset=utf-8');

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$recibido = $_SERVER['HTTP_X_CORRELATION_ID'] ?? '';
$correlationId = preg_match('/^[A-Za-z0-9._-]{1,100}$/D', $recibido) ? $recibido : null;
$id = preg_match('#^/estados/(\d+)$#', $ruta, $partes) ? (int) $partes[1] : null;

if ($id === 102) {
    // Si el orquestador corta a los 3 s, PHP intenta abortar el script y el servidor
    // integrado de PHP en Windows queda colgado. Así el servicio termina y sigue atendiendo.
    ignore_user_abort(true);
    sleep(5); // lentitud simulada
}

[$codigo, $cuerpo] = match ($id) {
    101, 102 => [200, ['solicitud_id' => $id, 'estado' => 'pendiente']],
    103 => [500, ['error' => 'Fallo interno simulado']],
    104 => [200, ['request' => 104, 'status' => 'pending']],
    default => [404, ['error' => 'Solicitud no encontrada']],
};

http_response_code($codigo);
if ($correlationId !== null) {
    header("X-Correlation-ID: {$correlationId}");
}
// Aparece en esta terminal: permite seguir la misma operación en cada servicio.
error_log("[estados] {$_SERVER['REQUEST_METHOD']} {$ruta} -> {$codigo} | correlation_id=".($correlationId ?? '(ninguno)'));

echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
