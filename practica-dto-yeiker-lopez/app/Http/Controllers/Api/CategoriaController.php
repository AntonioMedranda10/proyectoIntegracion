<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoriaController extends Controller
{
    /** GET /api/v1/categories → 200 OK. El cliente web la usa para llenar el selector. */
    public function __invoke(): AnonymousResourceCollection
    {
        return CategoriaResource::collection(Categoria::orderBy('nombre')->get());
    }
}
