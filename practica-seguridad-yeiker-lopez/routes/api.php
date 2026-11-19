<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MensajeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Público. Máximo 5 intentos por minuto: frena ataques de fuerza bruta (429).
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    // AUTENTICACIÓN: todo este grupo exige un token Bearer válido; si no, 401.
    // La AUTORIZACIÓN por rol o propietario está en App\Policies\MensajePolicy; si no, 403.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('/messages', [MensajeController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MensajeController::class, 'store'])->name('messages.store');
        Route::get('/messages/{mensaje}', [MensajeController::class, 'show'])->whereNumber('mensaje')->name('messages.show');
        Route::delete('/messages/{mensaje}', [MensajeController::class, 'destroy'])->whereNumber('mensaje')->name('messages.destroy');
    });
});
