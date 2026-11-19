<?php

use App\Http\Controllers\NotificacionPedidoController;
use Illuminate\Support\Facades\Route;

// Laravel antepone /api: las rutas completas empiezan con /api/v1.
Route::prefix('v1')->group(function () {
    // ASÍNCRONO: productor → cola → consumidor. Responde 202 Accepted al publicar.
    Route::post('/notificaciones/pedidos', [NotificacionPedidoController::class, 'encolar'])
        ->name('notificaciones.encolar');

    // SÍNCRONO (comparación): el mismo trabajo dentro de la petición. Responde 200 al terminar.
    Route::post('/notificaciones/pedidos/sincrono', [NotificacionPedidoController::class, 'enviarAhora'])
        ->name('notificaciones.sincrono');

    // Seguimiento: tabla de estados por correlationId o por messageId.
    Route::get('/notificaciones', [NotificacionPedidoController::class, 'index'])
        ->name('notificaciones.index');
    Route::get('/notificaciones/{messageId}', [NotificacionPedidoController::class, 'mostrar'])
        ->whereUuid('messageId')
        ->name('notificaciones.mostrar');
});
