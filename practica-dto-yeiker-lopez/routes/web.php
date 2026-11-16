<?php

use Illuminate\Support\Facades\Route;

// La raíz lleva al cliente web de prueba (public/cliente-web.html).
Route::get('/', fn () => redirect('/cliente-web.html'));
