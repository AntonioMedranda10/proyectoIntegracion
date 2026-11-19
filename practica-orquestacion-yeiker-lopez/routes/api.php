<?php

use App\Http\Controllers\ResumenSolicitudController;
use Illuminate\Support\Facades\Route;

// Laravel antepone /api: la ruta completa es GET /api/resumen-solicitud/{id}.
Route::get('/resumen-solicitud/{id}', ResumenSolicitudController::class)
    ->where('id', '[0-9]{1,9}')
    ->name('resumen-solicitud');
