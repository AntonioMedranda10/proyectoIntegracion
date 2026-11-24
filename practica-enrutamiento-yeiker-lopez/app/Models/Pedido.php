<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = ['cliente', 'total', 'estado'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }
}
