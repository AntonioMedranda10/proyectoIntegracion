# Contrato JSON – Registrar un pedido

**Proceso:** un cliente (web o móvil) registra un pedido de productos de la tienda.
**Actores:** cliente web, cliente móvil, backend (API Laravel), base de datos.
**Endpoint:** `POST /api/pedidos` · consulta: `GET /api/pedidos/{pedido_id}`
**Cabeceras:** `Content-Type: application/json` y `Accept: application/json`

## 1. Contrato de solicitud (cliente → backend)

```json
{
  "usuario_id": 1,
  "metodo_pago": "tarjeta",
  "direccion_entrega": "Av. 4 de Noviembre y calle 15, Manta",
  "items": [
    { "producto_id": 1, "cantidad": 2 },
    { "producto_id": 2, "cantidad": 1 }
  ],
  "observacion": "Entregar en horario de la tarde"
}
```

| Campo | Tipo | ¿Obligatorio? | Reglas | Significado |
|---|---|---|---|---|
| `usuario_id` | integer | Sí | número (no texto), debe existir | Usuario que realiza el pedido |
| `metodo_pago` | string | Sí | `tarjeta` · `efectivo` · `transferencia` | Forma de pago elegida |
| `direccion_entrega` | string | Sí | 5 a 200 caracteres | Dónde se entrega el pedido |
| `items` | array | Sí | 1 a 20 elementos | Productos del pedido |
| `items[].producto_id` | integer | Sí | número, debe existir, sin repetir | Producto que se compra |
| `items[].cantidad` | integer | Sí | número entre 1 y 50 | Unidades de ese producto |
| `observacion` | string / null | No | máx. 255 caracteres | Indicación adicional para la entrega |

Los campos que no están en el contrato (por ejemplo `total` o `precio_unitario`) **se ignoran**: el precio siempre se toma de la base de datos.

## 2. Contrato de respuesta exitosa (backend → cliente)

`201 Created` (registro) · `200 OK` (consulta). Cabecera `Location: /api/pedidos/{pedido_id}` al registrar.

```json
{
  "data": {
    "pedido_id": 1,
    "usuario_id": 1,
    "estado": "pendiente",
    "metodo_pago": "tarjeta",
    "direccion_entrega": "Av. 4 de Noviembre y calle 15, Manta",
    "observacion": "Entregar en horario de la tarde",
    "total": 138.3,
    "moneda": "USD",
    "cantidad_productos": 3,
    "fecha_registro": "2026-11-15T14:02:15-05:00",
    "items": [
      { "producto_id": 1, "nombre": "Audífonos Bluetooth", "cantidad": 2, "precio_unitario": 59.9, "subtotal": 119.8 },
      { "producto_id": 2, "nombre": "Mouse inalámbrico", "cantidad": 1, "precio_unitario": 18.5, "subtotal": 18.5 }
    ]
  },
  "mensaje": "Pedido registrado correctamente."
}
```

| Campo | Tipo | ¿Siempre presente? | Significado |
|---|---|---|---|
| `data.pedido_id` | integer | Sí | Identificador único del pedido |
| `data.usuario_id` | integer | Sí | Usuario dueño del pedido |
| `data.estado` | string | Sí | `pendiente` al registrarse |
| `data.metodo_pago` | string | Sí | Método de pago registrado |
| `data.direccion_entrega` | string | Sí | Dirección registrada |
| `data.observacion` | string / null | Sí (puede ser `null`) | Indicación adicional |
| `data.total` | number | Sí | Suma de subtotales, calculada por el backend |
| `data.moneda` | string | Sí | Moneda del total (`USD`) |
| `data.cantidad_productos` | integer | Sí | Suma de las cantidades |
| `data.fecha_registro` | string (ISO 8601 con zona) | Sí | Fecha y hora del registro |
| `data.items[].producto_id` | integer / null | Sí | Producto comprado (`null` si luego se elimina) |
| `data.items[].nombre` | string | Sí | Nombre al momento de la compra |
| `data.items[].cantidad` | integer | Sí | Unidades |
| `data.items[].precio_unitario` | number | Sí | Precio al momento de la compra |
| `data.items[].subtotal` | number | Sí | `precio_unitario × cantidad` |
| `mensaje` | string | Solo al registrar | Confirmación legible |

## 3. Contrato de error

| Código | Cuándo | Cuerpo |
|---|---|---|
| `422 Unprocessable Content` | Falta un campo, tipo incorrecto, valor no permitido, stock insuficiente | `{ "message": "...", "errors": { "campo": ["detalle"] } }` |
| `400 Bad Request` | El cuerpo no es un JSON válido | `{ "message": "El cuerpo de la solicitud no es un JSON válido.", "detalle": "..." }` |
| `404 Not Found` | El pedido consultado no existe | `{ "message": "El recurso solicitado no existe." }` |

En `errors`, la clave es la ruta del campo (`items.0.cantidad` = cantidad del primer producto). Ningún error guarda datos.
