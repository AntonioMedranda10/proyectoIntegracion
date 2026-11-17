<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * TRADUCTOR (adaptador) de productos.
 *
 * Convierte los productos que llegan de dos orígenes con estructuras distintas al
 * MISMO contrato común del backend, el de la práctica 2.2:
 *
 *     { name, description, price, stock, categoryId }
 *
 * Hay un método por origen. Si un valor falta, está mal formado o es ambiguo, se
 * rechaza la entrada con un error explícito que usa el nombre del campo de ESE
 * origen; nunca se adivina ni se convierte un valor inválido en silencio.
 */
class NormalizadorProductos
{
    /** Orígenes que sabe traducir (se usan en las rutas) y cómo se nombran en los mensajes. */
    public const ORIGENES = [
        'inventory' => 'la hoja de inventario',
        'supplier' => 'el proveedor externo',
    ];

    /** Tabla de equivalencias: código de categoría del proveedor → categoría del catálogo. */
    private const CATEGORIAS_DEL_PROVEEDOR = [
        'ELEC' => 'Electrónica',
        'HOME' => 'Hogar',
        'ACC' => 'Accesorios',
    ];

    /** @var array<string, string[]> errores encontrados en la entrada que se está traduciendo */
    private array $errores = [];

    public function normalizar(string $origen, array $entrada): array
    {
        return match ($origen) {
            'inventory' => $this->desdeInventario($entrada),
            'supplier' => $this->desdeProveedor($entrada),
            default => throw new InvalidArgumentException("Origen desconocido: {$origen}"),
        };
    }

    /**
     * Origen A: hoja de inventario exportada de Excel. Todas las celdas llegan como
     * texto, el precio con coma decimal y la categoría escrita por su nombre.
     */
    public function desdeInventario(array $entrada): array
    {
        $this->errores = [];

        return $this->resultado([
            'name' => $this->textoObligatorio($entrada, 'nombre_producto'),
            'description' => $this->textoOpcional($entrada, 'descripcion'),
            'price' => $this->precioConComa($entrada, 'precio'),
            'stock' => $this->cantidadEnTexto($entrada, 'existencias'),
            'categoryId' => $this->categoriaPorNombre($entrada, 'categoria'),
        ]);
    }

    /**
     * Origen B: API de un proveedor mayorista (simulada). Usa inglés, datos anidados,
     * el precio en centavos con su moneda y la categoría como código.
     */
    public function desdeProveedor(array $entrada): array
    {
        $this->errores = [];

        if (! is_array($entrada['product'] ?? null)) {
            $this->error('product', 'falta el objeto con los datos del producto.');

            return $this->resultado([]);
        }

        return $this->resultado([
            'name' => $this->textoObligatorio($entrada, 'product.title'),
            'description' => $this->textoOpcional($entrada, 'product.details'),
            'price' => $this->precioEnCentavos($entrada, 'product.pricing'),
            'stock' => $this->cantidadEntera($entrada, 'product.inventory.available'),
            'categoryId' => $this->categoriaPorCodigo($entrada, 'product.category_code'),
        ]);
    }

    // ---------------------------------------------------------------- reglas de traducción

    private function textoObligatorio(array $entrada, string $campo): ?string
    {
        $valor = Arr::get($entrada, $campo);

        if ($valor === null || (is_string($valor) && trim($valor) === '')) {
            return $this->error($campo, 'es obligatorio.');
        }
        if (! is_string($valor)) {
            return $this->error($campo, 'debe ser texto.');
        }

        return trim($valor);
    }

    /** Texto vacío o campo ausente significan lo mismo: sin valor (null). */
    private function textoOpcional(array $entrada, string $campo): ?string
    {
        $valor = Arr::get($entrada, $campo);

        if ($valor === null) {
            return null;
        }
        if (! is_string($valor)) {
            return $this->error($campo, 'debe ser texto o estar vacío.');
        }

        return trim($valor) === '' ? null : trim($valor);
    }

    /** "35,50" → 35.5. En esta hoja el decimal es la coma: "35.50" se considera ambiguo. */
    private function precioConComa(array $entrada, string $campo): ?float
    {
        $valor = Arr::get($entrada, $campo);

        if ($valor === null || $valor === '') {
            return $this->error($campo, 'es obligatorio. Ejemplo: "35,50".');
        }
        if (! is_string($valor)) {
            return $this->error($campo, 'debe llegar como texto, igual que en la hoja. Ejemplo: "35,50".');
        }

        $texto = trim($valor);
        if (preg_match('/^\d+\.\d{1,2}$/', $texto)) {
            return $this->error($campo, "\"{$texto}\" es ambiguo: esta hoja usa coma decimal. Escriba \"".str_replace('.', ',', $texto).'".');
        }
        if (! preg_match('/^\d+(,\d{1,2})?$/', $texto)) {
            return $this->error($campo, "\"{$texto}\" no es un monto válido: use solo dígitos y coma decimal (máximo 2 decimales, sin separador de miles). Ejemplo: \"35,50\".");
        }

        $monto = round((float) str_replace(',', '.', $texto), 2);

        return $monto > 0 ? $monto : $this->error($campo, 'debe ser mayor que 0.');
    }

    /** "20" → 20. Solo dígitos: "20.5", "-3" o "veinte" se rechazan. */
    private function cantidadEnTexto(array $entrada, string $campo): ?int
    {
        $valor = Arr::get($entrada, $campo);

        if ($valor === null || $valor === '') {
            return $this->error($campo, 'es obligatorio. Ejemplo: "20".');
        }
        if (! is_string($valor)) {
            return $this->error($campo, 'debe llegar como texto, igual que en la hoja. Ejemplo: "20".');
        }

        $texto = trim($valor);
        if (! preg_match('/^\d+$/', $texto)) {
            return $this->error($campo, "\"{$texto}\" no es una cantidad válida: use solo dígitos, sin decimales ni signos. Ejemplo: \"20\".");
        }

        return (int) $texto;
    }

    /** { "amount_cents": 3550, "currency": "USD" } → 35.5. No se convierte desde otras monedas. */
    private function precioEnCentavos(array $entrada, string $campo): ?float
    {
        $centavos = Arr::get($entrada, "{$campo}.amount_cents");
        $moneda = Arr::get($entrada, "{$campo}.currency");

        $precio = match (true) {
            $centavos === null => $this->error("{$campo}.amount_cents", 'es obligatorio (precio en centavos). Ejemplo: 3550 = $35,50.'),
            ! is_int($centavos) => $this->error("{$campo}.amount_cents", 'debe ser un número entero de centavos, no texto ni decimales. Ejemplo: 3550.'),
            $centavos <= 0 => $this->error("{$campo}.amount_cents", 'debe ser mayor que 0.'),
            default => round($centavos / 100, 2),
        };

        if ($moneda !== 'USD') {
            $precio = $this->error("{$campo}.currency", $moneda === null
                ? 'es obligatorio. Ejemplo: "USD".'
                : json_encode($moneda, JSON_UNESCAPED_UNICODE).' no se acepta: solo precios en USD (el traductor no convierte monedas).');
        }

        return $precio;
    }

    private function cantidadEntera(array $entrada, string $campo): ?int
    {
        $valor = Arr::get($entrada, $campo);

        if ($valor === null) {
            return $this->error($campo, 'es obligatorio. Ejemplo: 20.');
        }
        if (! is_int($valor) || $valor < 0) {
            return $this->error($campo, 'debe ser un número entero mayor o igual a 0, no texto. Ejemplo: 20.');
        }

        return $valor;
    }

    /** "Electrónica" → 1. Sin distinguir mayúsculas; si no existe, se listan los nombres válidos. */
    private function categoriaPorNombre(array $entrada, string $campo): ?int
    {
        $nombre = $this->textoOpcional($entrada, $campo);
        if ($nombre === null) {
            return null;
        }

        $categorias = Categoria::orderBy('nombre')->get();
        $categoria = $categorias->first(fn (Categoria $c) => mb_strtolower($c->nombre) === mb_strtolower($nombre));

        return $categoria?->id ?? $this->error(
            $campo,
            "la categoría \"{$nombre}\" no existe. Valores válidos: {$categorias->pluck('nombre')->join(', ')}."
        );
    }

    /** "ELEC" → 1, usando la tabla de equivalencias del proveedor. */
    private function categoriaPorCodigo(array $entrada, string $campo): ?int
    {
        $codigo = Arr::get($entrada, $campo);
        if ($codigo === null || $codigo === '') {
            return null;
        }

        $nombre = is_string($codigo) ? (self::CATEGORIAS_DEL_PROVEEDOR[$codigo] ?? null) : null;
        if ($nombre === null) {
            return $this->error($campo, 'código desconocido: '.json_encode($codigo, JSON_UNESCAPED_UNICODE)
                .'. Valores válidos: '.implode(', ', array_keys(self::CATEGORIAS_DEL_PROVEEDOR)).'.');
        }

        return Categoria::where('nombre', $nombre)->value('id')
            ?? $this->error($campo, "la categoría \"{$nombre}\" (código {$codigo}) no está en el catálogo.");
    }

    // ---------------------------------------------------------------- errores

    /** Anota un error con el nombre del campo del origen y devuelve null para que no se use. */
    private function error(string $campo, string $mensaje): null
    {
        $this->errores[$campo][] = "{$campo}: {$mensaje}";

        return null;
    }

    /** Con algún error se rechaza la entrada completa: 422 con el detalle de cada campo. */
    private function resultado(array $contrato): array
    {
        if ($this->errores) {
            throw ValidationException::withMessages($this->errores);
        }

        return $contrato;
    }
}
