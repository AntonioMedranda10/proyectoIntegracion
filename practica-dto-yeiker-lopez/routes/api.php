<?php

use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\ProductoController;
use Illuminate\Support\Facades\Route;

// Versión 1 del contrato. Un cambio incompatible se publicaría como /v2 para que
// el cliente web y el móvil migren cada uno a su ritmo.
Route::prefix('v1')->group(function () {
    Route::post('/products', [ProductoController::class, 'store'])->name('products.store');
    Route::get('/products/{producto}', [ProductoController::class, 'show'])->name('products.show');
    Route::get('/categories', CategoriaController::class)->name('categories.index');
});
