<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    public $timestamps = false;

    protected $table = 'pedido_items';

    protected $fillable = ['pedido_id', 'producto_id', 'cantidad', 'precio_unitario'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
