<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salida del consumidor de Logística. */
class Envio extends Model
{
    public $timestamps = false;

    protected $table = 'envios';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['pedido_id', 'guia', 'transportista', 'evento_id', 'registrado_en'];

    protected function casts(): array
    {
        return ['registrado_en' => 'datetime'];
    }
}
