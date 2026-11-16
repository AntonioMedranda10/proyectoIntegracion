<?php

namespace App\DTO;

/**
 * DESERIALIZACIÓN: el JSON del cliente, ya validado, se convierte en un objeto
 * con tipos de PHP. De aquí hacia adentro nadie vuelve a leer el arreglo crudo
 * del request: se trabaja con propiedades tipadas que no se pueden modificar.
 */
final readonly class NuevoProductoDTO
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public float $precio,
        public int $stock,
        public ?int $categoriaId,
    ) {}

    /**
     * Traduce los nombres del contrato (name, price, categoryId...) a los del dominio.
     *
     * @param  array{name: string, description?: ?string, price: int|float, stock: int, categoryId?: ?int}  $datos
     */
    public static function desdeContrato(array $datos): self
    {
        return new self(
            nombre: $datos['name'],
            descripcion: $datos['description'] ?? null,
            precio: (float) $datos['price'],
            stock: $datos['stock'],
            categoriaId: $datos['categoryId'] ?? null,
        );
    }
}
