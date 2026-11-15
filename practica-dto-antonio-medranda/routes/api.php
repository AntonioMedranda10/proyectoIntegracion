<?php

use App\Http\Controllers\Api\PedidoController;
use Illuminate\Support\Facades\Route;

/*
| Proceso: Registrar un pedido (e-commerce del proyecto de aula).
| Contrato documentado en CONTRATO.md.
*/
Route::post('/pedidos', [PedidoController::class, 'store']);
Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
