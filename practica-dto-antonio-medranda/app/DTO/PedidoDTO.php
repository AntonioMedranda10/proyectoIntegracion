<?php

namespace App\DTO;

use App\Http\Requests\RegistrarPedidoRequest;

/**
 * DTO de entrada de "Registrar un pedido".
 *
 * DESERIALIZACIÓN: el JSON del cliente (texto) ya validado se convierte en un
 * objeto inmutable con tipos de PHP. A partir de aquí el servicio trabaja con
 * propiedades tipadas ($dto->cantidad es int), no con un array suelto.
 */
final readonly class PedidoDTO
{
    /** @param list<ItemPedidoDTO> $items */
    public function __construct(
        public int $usuarioId,
        public string $metodoPago,
        public string $direccionEntrega,
        public array $items,
        public ?string $observacion,
    ) {}

    public static function desdeRequest(RegistrarPedidoRequest $request): self
    {
        // validated() solo devuelve los campos del contrato: los campos extra se descartan.
        $datos = $request->validated();

        return new self(
            usuarioId: $datos['usuario_id'],
            metodoPago: $datos['metodo_pago'],
            direccionEntrega: trim($datos['direccion_entrega']),
            items: array_map(ItemPedidoDTO::desdeArray(...), $datos['items']),
            observacion: $datos['observacion'] ?? null,
        );
    }
}
