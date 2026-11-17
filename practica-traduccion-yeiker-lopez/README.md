# Práctica 2.3 – Patrones de traducción y normalización

**Estudiante:** Yeiker Lopez · **Asignatura:** IS-803 Integración e Implementación de Software · **Tema 2.3**

Módulo individual del proyecto de aula (e-commerce de 5 nodos). Parte del módulo de la práctica 2.2, cuyo contrato de productos ([CONTRATO.md](CONTRATO.md)) es el **contrato común** del backend, y agrega un **traductor** que recibe el mismo producto desde dos orígenes con estructuras distintas y los convierte a ese contrato **sin modificarlo**. El análisis completo (diferencias, tabla de mapeo, reglas, errores y diagrama) está en [TRADUCCION.md](TRADUCCION.md).

## Flujo

```
Origen A (hoja de inventario) ─┐                                 ┌─► /normalize → 200 con el contrato común
                               ├─► NormalizadorProductos ─► contrato común ─┤
Origen B (proveedor externo) ──┘    (un método por origen)   (reglas 2.2)  └─► /import → RegistrarProducto (2.2) → 201
                                         │
                                         └─► 422 con errores explícitos por campo del origen
```

## Archivos de esta práctica

| Archivo | Rol |
|---|---|
| `app/Services/NormalizadorProductos.php` | **Traductor**: `desdeInventario()` y `desdeProveedor()` devuelven el mismo contrato |
| `app/Http/Controllers/Api/ImportacionController.php` | Pide el contrato al traductor y lo valida con las reglas de la 2.2 |
| `routes/api.php` | `POST /api/v1/normalize/{origen}` y `POST /api/v1/import/{origen}` (`inventory` o `supplier`) |
| `tests/Feature/TraduccionProductosTest.php` | P1, P2, P3 y casos extra por HTTP (10 pruebas) |
| `tests/Feature/NormalizadorProductosTest.php` | El traductor probado directamente, sin HTTP (3 pruebas) |
| `public/traductor.html` | Página que traduce los dos orígenes y compara las salidas campo por campo |
| `postman/Practica_Traduccion_Productos.postman_collection.json` | P1, P2, P3 e importación en Postman |
| `docs/diagrama-traduccion.png` | Diagrama de componentes |

Se reutilizan sin cambios los archivos de la 2.2: `RegistrarProductoRequest` (reglas del contrato), `NuevoProductoDTO`, `RegistrarProducto` y `ProductoResource`. Sus 8 pruebas siguen pasando.

## Cómo ejecutarlo

Requisitos: PHP 8.2 o superior con la extensión `pdo_sqlite`, y Composer.

```bash
cd practica-traduccion-yeiker-lopez
composer install
copy .env.example .env                    # Linux/macOS: cp .env.example .env
php artisan key:generate
php -r "touch('database/database.sqlite');"
php artisan migrate:fresh --seed          # crea la base SQLite con datos ficticios
php artisan serve
```

- API: `http://127.0.0.1:8000/api/v1`
- Comparador de orígenes: `http://127.0.0.1:8000/traductor.html`

## Pruebas

- **Automáticas:** `php artisan test` (21 pruebas: 13 de esta práctica y 8 del contrato de la 2.2).
- **Postman:** importar `postman/Practica_Traduccion_Productos.postman_collection.json` y ejecutar en orden. P2 compara su salida con la de P1.
- **Comparador:** en `traductor.html`, "Traducir ambos" con el ejemplo válido muestra que las dos salidas son idénticas; "Cargar ejemplo con errores" muestra los 422.

Para repetir desde cero: `php artisan migrate:fresh --seed`.

## Datos de prueba (ficticios)

| id | Categoría | Código del proveedor |
|---|---|---|
| 1 | Electrónica | `ELEC` |
| 2 | Hogar | `HOME` |
| 3 | Accesorios | `ACC` |

En el repositorio no hay archivos `.env`, contraseñas ni tokens.
