<?php

namespace App\DTO;

/**
 * Un producto dentro del pedido, tal como lo envía el cliente.
 */
final readonly class ItemPedidoDTO
{
    public function __construct(
        public int $productoId,
        public int $cantidad,
    ) {}

    /** @param array{producto_id: int, cantidad: int} $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            productoId: $datos['producto_id'],
            cantidad: $datos['cantidad'],
        );
    }
}
