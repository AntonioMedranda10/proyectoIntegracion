<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salida del consumidor de Facturación. */
class Comprobante extends Model
{
    public $timestamps = false;

    protected $table = 'comprobantes';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['numero', 'pedido_id', 'monto', 'metodo', 'evento_id', 'emitido_en'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'emitido_en' => 'datetime'];
    }
}
