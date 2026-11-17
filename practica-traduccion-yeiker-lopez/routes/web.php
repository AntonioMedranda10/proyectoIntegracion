<?php

use Illuminate\Support\Facades\Route;

// La raíz lleva a la página que compara los dos orígenes (public/traductor.html).
Route::get('/', fn () => redirect('/traductor.html'));
