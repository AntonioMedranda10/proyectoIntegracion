# Práctica individual – DTO y contratos de intercambio JSON

**Estudiante:** Antonio Medranda · **Asignatura:** IS-803 Integración e Implementación de Software · **Tema 2.2**

Módulo individual del proyecto de aula (e-commerce). Implementa en Laravel el proceso **"Registrar un pedido"**, con su contrato JSON, validación, deserialización a DTO y serialización con API Resources. El contrato completo está en [CONTRATO.md](CONTRATO.md).

## Flujo

```
Cliente web / móvil ──JSON──► POST /api/pedidos
   ContratoJson (middleware)     → rechaza JSON mal formado (400)
   RegistrarPedidoRequest        → valida campos y tipos (422 si falla)
   PedidoDTO                     → deserializa a objeto tipado
   RegistrarPedidoService        → regla de negocio + transacción (SQLite)
   PedidoResource                → serializa al JSON del contrato
Cliente ◄──JSON (201)──
```

| Archivo | Rol |
|---|---|
| `routes/api.php` | Rutas `POST /api/pedidos` y `GET /api/pedidos/{id}` |
| `app/Http/Requests/RegistrarPedidoRequest.php` | Contrato de solicitud: reglas de validación |
| `app/DTO/PedidoDTO.php`, `ItemPedidoDTO.php` | Deserialización a objetos con tipos |
| `app/Services/RegistrarPedidoService.php` | Precios desde la base, stock y transacción |
| `app/Http/Resources/PedidoResource.php` | Contrato de respuesta: serialización |
| `app/Http/Middleware/ContratoJson.php` | JSON mal formado → 400 y salida UTF-8 legible |
| `tests/Feature/RegistrarPedidoTest.php` | 8 pruebas automáticas del contrato |
| `postman/Practica_DTO_Pedidos.postman_collection.json` | Pruebas P1–P3 y adicionales |

## Cómo ejecutarlo

Requisitos: PHP 8.3+ con la extensión `pdo_sqlite`, y Composer.

```bash
cd practica-dto-antonio-medranda
composer install
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed   # crea la base SQLite con datos ficticios
php artisan serve --port=8088
```

La API queda en `http://127.0.0.1:8088/api`.

**Pruebas automáticas:** `php artisan test`

**Pruebas en Postman:** importar `postman/Practica_DTO_Pedidos.postman_collection.json` y ejecutar en orden. Para repetirlas desde cero: `php artisan migrate:fresh --seed`.

## Datos de prueba (ficticios)

| id | Usuario | | id | Producto | Precio | Stock |
|---|---|---|---|---|---|---|
| 1 | Cliente Web Demo | | 1 | Audífonos Bluetooth | 59.90 | 15 |
| 2 | Cliente Móvil Demo | | 2 | Mouse inalámbrico | 18.50 | 30 |
| | | | 3 | Teclado mecánico | 45.99 | 10 |
| | | | 4 | Cargador USB-C 65W | 24.00 | 3 |
