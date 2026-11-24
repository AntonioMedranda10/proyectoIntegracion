<?php

use App\Http\Controllers\EventoController;
use Illuminate\Support\Facades\Route;

// Laravel antepone /api: las rutas completas empiezan con /api/v1.
Route::prefix('v1')->group(function () {
    // Canal de entrada: validación → filtro → enrutador → cola del consumidor (o no_enrutables).
    Route::post('/eventos', [EventoController::class, 'recibir'])->name('eventos.recibir');

    // Recorrido registrado de los eventos (filtro, destino y resultado del consumidor).
    Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');
    Route::get('/eventos/{eventoId}', [EventoController::class, 'mostrar'])
        ->whereNumber('eventoId')
        ->name('eventos.mostrar');

    // Tabla de reglas que aplican el filtro y el enrutador (las mismas del código).
    Route::get('/reglas', [EventoController::class, 'reglas'])->name('reglas');
});
