# Plan Frontend + Contrato de API (versión prueba en clase)

**Proyecto:** E-commerce, arquitectura de 5 nodos
**Objetivo de esta etapa:** demostrar que **L2 → L1 → L3 → L4 / L5** se comunican. No tiene que estar completo.

---

## 1. Reglas de conexión (importante)

| Regla | Detalle |
|---|---|
| URL base de la API | `http://192.168.10.11/api` (siempre a través de NGINX / L1) |
| Nunca llamar directo a | la IP del backend, PostgreSQL ni el servidor de archivos |
| Configuración en Vite | usar variable de entorno `VITE_API_URL` (no escribir la IP en el código) |
| Autenticación | header `Authorization: Bearer <access_token>` |
| Dónde guardar el token | `localStorage` |
| Formato | JSON en todo, excepto la subida de imagen (`multipart/form-data`) |
| Imágenes | el backend devuelve `imageUrl` (ej. `/files/abc123.jpg`); se muestra como `http://192.168.10.11/files/abc123.jpg` |

> **Mientras NGINX no esté listo:** se puede probar poniendo `VITE_API_URL=http://<IP-del-backend>:3001/api`. El backend tendrá CORS habilitado. En la prueba final se cambia a la IP de L1.

**Formato de error (igual en todos los endpoints):**
```json
{ "statusCode": 401, "message": "Credenciales inválidas", "error": "Unauthorized" }
```
- `400` → datos inválidos (`message` puede ser un arreglo de textos)
- `401` → no ha iniciado sesión o el token venció → mandar al login
- `403` → no tiene permiso (ej. cliente intentando entrar a admin)
- `404` → no existe

---

## 2. Roles

| Rol | Cómo se obtiene | Qué puede hacer |
|---|---|---|
| **Visitante** | sin iniciar sesión | ver estado del sistema, ver catálogo y detalle de producto, registrarse, iniciar sesión |
| **Cliente** (`customer`) | se registra desde la app | todo lo del visitante + ver su perfil *(y en fase 2: hacer pedidos)* |
| **Administrador** (`admin`) | usuario creado por el backend | todo lo anterior + crear, editar, eliminar productos y subir imágenes |

**Usuario admin de prueba:** `admin@tienda.com` / `Admin123!`

---

## 3. Pantallas

### Mínimo para la prueba en clase (prioridad)

| # | Pantalla | Ruta sugerida | Rol | Qué prueba |
|---|---|---|---|---|
| 1 | **Estado del sistema** | `/estado` | todos | L1 → L3 → L4 y L5. Muestra qué instancia respondió (backend-1 / backend-2) |
| 2 | Login | `/login` | visitante | autenticación JWT |
| 3 | Registro | `/registro` | visitante | escritura en la base de datos |
| 4 | Catálogo | `/` | todos | lectura desde PostgreSQL |
| 5 | Detalle de producto | `/producto/:id` | todos | lectura por id |
| 6 | Admin: productos | `/admin/productos` | admin | crear / editar / eliminar + **subir imagen** (prueba L5) |

**Detalle de cada pantalla:**

1. **Estado del sistema:** botón "Consultar" que llama a `GET /health` y muestra `instance`, `database` y `files`. Al presionarlo varias veces debe alternar entre `backend-1` y `backend-2`: eso demuestra el balanceo. *Es la pantalla más importante para la demo.*
2. **Login:** email + contraseña. Guarda `access_token` y `user`. Si `role === "admin"` mostrar el enlace a Admin.
3. **Registro:** nombre, email, contraseña. Al registrarse queda con sesión iniciada (devuelve token).
4. **Catálogo:** tarjetas con imagen, nombre y precio. Campo de búsqueda opcional.
5. **Detalle:** imagen, nombre, descripción, precio, stock.
6. **Admin productos:** tabla de productos + formulario (nombre, descripción, precio, stock) + input de archivo para la imagen.

Componente común: **barra superior** con enlaces (Catálogo, Estado, Login / Cerrar sesión, Admin si es admin).

### Fase 2 (después de la prueba)
- Carrito y crear pedido (`/carrito`), Mis pedidos (`/mis-pedidos`), Admin pedidos.
- Pagos, mensajes y notificaciones.

---

## 4. Endpoints

Todos empiezan con `http://192.168.10.11/api`.
🔓 = público · 🔑 = requiere token · 👑 = requiere token de admin

### 4.1 Health

**🔓 `GET /health`**
```json
{
  "status": "ok",
  "instance": "backend-1",
  "port": 3001,
  "host": "nombre-del-pc",
  "database": "up",
  "files": "up",
  "timestamp": "2026-09-29T15:00:00.000Z"
}
```
- `database` y `files` vienen como `"down"` si ese nodo no responde. En ese caso `status` es `"degraded"`.
- Además, **todas** las respuestas de la API traen el header `X-Backend-Instance` con la instancia que respondió.

### 4.2 Auth

**🔓 `POST /auth/register`**
```json
// Envío
{ "name": "Ana Pérez", "email": "ana@correo.com", "password": "123456" }
// Respuesta 201
{
  "access_token": "eyJhbGciOi...",
  "user": { "id": 2, "name": "Ana Pérez", "email": "ana@correo.com", "role": "customer" }
}
```
Validaciones: email válido, contraseña de mínimo 6 caracteres. Si el email ya existe → `409`.

**🔓 `POST /auth/login`**
```json
// Envío
{ "email": "ana@correo.com", "password": "123456" }
// Respuesta 200: misma forma que register
```

**🔑 `GET /auth/me`** → devuelve el objeto `user` del token (sirve para comprobar la sesión al recargar la página).

### 4.3 Catálogo (lectura pública)

**🔓 `GET /catalog/products?search=texto`** (`search` es opcional)
```json
[
  {
    "id": 1,
    "name": "Audífonos Bluetooth",
    "description": "Inalámbricos, 20h de batería",
    "price": 59.90,
    "stock": 15,
    "imageUrl": "/files/3f2a9c.jpg",
    "createdAt": "2026-09-29T15:00:00.000Z"
  }
]
```
`imageUrl` puede ser `null` si el producto no tiene imagen: mostrar una imagen por defecto.

Cada producto trae además su categoría: `"categoryId": 1` y `"category": { "id": 1, "name": "Electrónica", "description": "..." }`. Ambos son `null` si no tiene categoría.

**🔓 `GET /catalog/categories`** → lista de categorías `[{ "id": 1, "name": "Electrónica", "description": "..." }]` (para el filtro del catálogo y el formulario de producto).

**🔓 `GET /catalog/products?categoryId=1`** → productos de esa categoría (se puede combinar con `search`).

**🔓 `GET /catalog/products/:id`** → un solo producto (misma forma). `404` si no existe.

### 4.4 Productos (gestión, solo admin)

**👑 `POST /products`**
```json
{ "name": "Mouse gamer", "description": "RGB, 6 botones", "price": 35.5, "stock": 20 }
```
→ `201` con el producto creado.

**👑 `PATCH /products/:id`**: se envían solo los campos a cambiar. Devuelve el producto actualizado.

**👑 `DELETE /products/:id`** → `200` `{ "deleted": true }`

**👑 `POST /products/:id/image`** (sube la imagen; aquí participa el servidor de archivos L5)
- Tipo: `multipart/form-data`
- Campo: **`file`** (jpg/png, máx. 5 MB)
```js
const form = new FormData();
form.append('file', archivo);
axios.post(`${API}/products/${id}/image`, form, {
  headers: { Authorization: `Bearer ${token}` },
});
```
→ Respuesta: el producto con su `imageUrl` actualizado.

### 4.5 Fase 2 (ya disponibles en el backend, no son necesarios para la prueba)

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/orders` | 🔑 | crear pedido `{ "items": [{ "productId": 1, "quantity": 2 }] }` (descuenta stock) |
| GET | `/orders/my` | 🔑 | pedidos del usuario |
| GET | `/orders` | 👑 | todos los pedidos |
| POST | `/payments` | 🔑 | pago simulado `{ "orderId": 1, "method": "card" \| "cash" \| "transfer" }` |
| GET | `/payments/my` | 🔑 | pagos del usuario |
| GET | `/notifications` | 🔑 | notificaciones del usuario |
| PATCH | `/notifications/:id/read` | 🔑 | marcar notificación como leída |
| POST | `/messages` | 🔑 | mensaje a la tienda `{ "subject": "...", "content": "..." }` |
| GET | `/messages/my` | 🔑 | mensajes enviados por el usuario |
| GET | `/messages` | 👑 | todos los mensajes |
| GET | `/users` | 👑 | lista de usuarios |
| POST | `/categories` | 👑 | crear categoría `{ "name": "Juguetes", "description": "..." }` (409 si ya existe) |
| DELETE | `/categories/:id` | 👑 | eliminar categoría (sus productos quedan sin categoría) |
| GET | `/audit` | 👑 | últimas 100 operaciones que modificaron datos: usuario, ruta, código, instancia y fecha |

Al crear o editar un producto se puede enviar `"categoryId": 1` (opcional). Si la categoría no existe responde 400.

Documentación interactiva de todos los endpoints (Swagger): `http://192.168.10.11/api/docs`

---

## 5. Guion de la demo en clase

1. Abrir `http://192.168.10.11/` → carga el frontend (**L1 → L2**).
2. Ir a **Estado** y presionar varias veces → responde `backend-1` / `backend-2` con base de datos y archivos `up` (**L1 → L3 → L4 / L5**, balanceo).
3. **Registrarse** como cliente → el usuario queda guardado (**escritura en L4**).
4. **Login como admin** → crear un producto y **subir su imagen** (**L3 → L5**, metadatos en **L4**).
5. Volver al **Catálogo** → aparece el producto con su imagen (imagen servida por **L1 → L5**).

Si esos 5 pasos funcionan, los 5 nodos se comunican correctamente.
