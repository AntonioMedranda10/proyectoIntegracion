<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo interno: las columnas están en español (nombre, precio, categoria_id).
 * El contrato público usa otros nombres (name, price, categoryId); la traducción
 * la hacen el DTO (entrada) y ProductoResource (salida).
 */
class Producto extends Model
{
    protected $table = 'productos';

    // imagen_url no se llena aquí: la imagen se sube después, en otro proceso.
    protected $fillable = ['nombre', 'descripcion', 'precio', 'stock', 'categoria_id'];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'categoria_id' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
