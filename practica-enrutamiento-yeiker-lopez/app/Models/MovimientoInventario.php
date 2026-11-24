<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salida del consumidor de Inventario. */
class MovimientoInventario extends Model
{
    public $timestamps = false;

    protected $table = 'movimientos_inventario';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['producto_id', 'pedido_id', 'cantidad', 'motivo', 'stock_resultante', 'evento_id', 'registrado_en'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'stock_resultante' => 'integer', 'registrado_en' => 'datetime'];
    }
}
