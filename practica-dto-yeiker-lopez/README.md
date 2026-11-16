# Práctica 2.2 – DTO y contratos de intercambio JSON

**Estudiante:** Yeiker Lopez · **Asignatura:** IS-803 Integración e Implementación de Software · **Tema 2.2**

Módulo individual del proyecto de aula (e-commerce de 5 nodos). Implementa en Laravel el proceso **"Registrar un producto"**, con su contrato JSON, validación, deserialización a DTO y serialización con API Resources. El contrato completo (tablas, códigos HTTP, diagrama y análisis de ambigüedades) está en [CONTRATO.md](CONTRATO.md).

## Flujo

```
Cliente web / móvil ──JSON──► POST /api/v1/products
   RegistrarProductoRequest   → valida campos y tipos (422 si falla, no guarda nada)
   NuevoProductoDTO           → deserializa a un objeto tipado
   RegistrarProducto          → servicio: guarda con los nombres internos (SQLite)
   ProductoResource           → serializa al JSON del contrato
Cliente ◄──JSON (201)──
```

## Archivos del módulo

| Archivo | Rol |
|---|---|
| `routes/api.php` | `POST /api/v1/products`, `GET /api/v1/products/{id}` y `GET /api/v1/categories` |
| `app/Http/Requests/RegistrarProductoRequest.php` | Contrato de solicitud: reglas y mensajes de validación |
| `app/DTO/NuevoProductoDTO.php` | Deserialización: JSON validado → objeto tipado |
| `app/Actions/RegistrarProducto.php` | Servicio del caso de uso |
| `app/Http/Resources/ProductoResource.php` | Contrato de respuesta: serialización |
| `app/Http/Controllers/Api/ProductoController.php` | Controlador que une los pasos |
| `bootstrap/app.php` | La API responde siempre en JSON (también 422 y 404) |
| `database/migrations/`, `database/seeders/` | Tablas `categorias` y `productos` y datos ficticios |
| `tests/Feature/RegistrarProductoTest.php` | 8 pruebas automáticas (P1, P2, P3 y casos extra) |
| `public/cliente-web.html` | Cliente web que consume el contrato |
| `postman/Practica_DTO_Productos.postman_collection.json` | Pruebas en Postman (simula el cliente móvil) |

## Cómo ejecutarlo

Requisitos: PHP 8.2 o superior con la extensión `pdo_sqlite`, y Composer.

```bash
cd practica-dto-yeiker-lopez
composer install
copy .env.example .env                    # Linux/macOS: cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate:fresh --seed          # crea la base SQLite con datos ficticios
php artisan serve
```

- API: `http://127.0.0.1:8000/api/v1`
- Cliente web: `http://127.0.0.1:8000/cliente-web.html`

## Pruebas

- **Automáticas:** `php artisan test`
- **Postman:** importar `postman/Practica_DTO_Productos.postman_collection.json` y ejecutar las peticiones en orden. Cada una trae verificaciones en la pestaña *Test Results*.
- **Cliente web:** registrar un producto válido (201). Después, marcar "Enviar los números como texto" para ver el 422 que recibiría un cliente que no respeta el contrato.

Para repetir las pruebas desde cero: `php artisan migrate:fresh --seed`. Si se registra dos veces el mismo nombre, el resultado correcto es 422 ("Ya existe un producto con ese nombre").

## Clientes web y móvil

El proyecto todavía no tiene app móvil. Por eso **Postman hace el papel del cliente móvil** y `cliente-web.html` el del cliente web. Los dos no comparten código: comparten el **contrato**, que es lo que garantiza que interpreten igual cada campo.

## Datos de prueba (ficticios)

| id | Categoría | | id | Producto | Precio | Stock |
|---|---|---|---|---|---|---|
| 1 | Electrónica | | 1 | Audífonos Bluetooth | 59.90 | 15 |
| 2 | Hogar | | 2 | Lámpara LED de escritorio | 24.50 | 0 |
| 3 | Accesorios | | | | | |

En el repositorio no hay archivos `.env`, contraseñas ni tokens. En el proyecto real solo el administrador (con token JWT) puede registrar productos; esta práctica se centra en el contrato y no pide autenticación.
