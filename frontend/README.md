# Frontend – Nodo L2 (React + Vite)

Tienda del proyecto de 5 nodos. El frontend **solo habla con NGINX (L1)**:

```
Navegador ──► NGINX (L1) ──► /        → L2 (este frontend)
                         ──► /api/    → L3 (backend, 2 instancias)
                         ──► /files/  → L5 (imágenes)
```

## Configuración: una sola dirección

La única dirección es la de NGINX (L1), en `NGINX_URL`. En el código no hay ninguna IP.

El navegador siempre pide rutas relativas (`/api/...`, `/files/...`):

- Con `npm run dev` y `npm run preview`, Vite las reenvía a `NGINX_URL` (proxy). No hace falta CORS,
  y funciona igual si se abre el frontend en `localhost:5173`, con la IP de este PC o a través de NGINX.
- En el despliegue final, NGINX (L1) sirve el frontend en `/` y enruta `/api` y `/files`.
  `nginx-l2.conf` también los reenvía a L1 por si alguien entra directo a este nodo.

| Archivo                  | Uso                                   | Valor                                              |
| ------------------------ | ------------------------------------- | -------------------------------------------------- |
| `.env.development`       | `npm run dev`                         | `NGINX_URL=http://<IP de L1>`, `VITE_BASE_URL` vacía |
| `.env.production`        | `npm run build` / `npm run preview`   | `VITE_BASE_URL` vacía; `NGINX_URL` solo para preview |
| `.env.development.local` | solo pruebas sin NGINX (no se sube)   | `NGINX_URL=http://localhost:3001`                  |

`VITE_BASE_URL` es opcional: fuerza una dirección absoluta en el navegador y se salta el proxy
(entonces NGINX debe permitir CORS).
Sin NGINX, la API funciona contra el backend directo, pero las imágenes no se ven (las sirve NGINX).
Después de cambiar un `.env`, reinicia `npm run dev`.

Fuera de este proyecto:

- Este PC tiene que estar en la misma red que L1 y llegar a `NGINX_URL`.
- El NGINX de L1 debe reenviar `/` a este PC (`proxy_pass http://<IP de este PC>:5173`, o `:4173`
  con preview, o `:80` con `nginx-l2.conf`), y el firewall de este PC debe permitir ese puerto.

## Ejecutar

```bash
npm install
npm run dev        # http://localhost:5173
npm run build      # genera dist/ para L2
npm run preview    # prueba el build en http://localhost:4173
```

Despliegue en L2: copiar `dist/` al servidor y usar `nginx-l2.conf` (incluye el fallback a
`index.html` para las rutas de React y el reenvío de `/api` y `/files` a L1).

## Pantallas

| Ruta                          | Rol       | Endpoint(s)                                             |
| ----------------------------- | --------- | ------------------------------------------------------- |
| `/`                           | todos     | `GET /catalog/products?search=` (búsqueda, filtros, orden) |
| `/producto/:id`               | todos     | `GET /catalog/products/:id`                             |
| `/estado`                     | todos     | `GET /health` + header `X-Backend-Instance`             |
| `/login`, `/registro`         | visitante | `POST /auth/login`, `POST /auth/register`               |
| `/carrito`                    | sesión    | `POST /orders` (el carrito vive en el navegador)        |
| `/pago/:orderId`              | sesión    | `POST /payments`, `GET /payments/my`                    |
| `/pedidos`                    | sesión    | `GET /orders/my`, `GET /payments/my`                    |
| `/notificaciones`             | sesión    | `GET /notifications`, `PATCH /notifications/:id/read`   |
| `/mensajes`                   | sesión    | `POST /messages`, `GET /messages/my`                    |
| `/admin/productos`            | admin     | `POST/PATCH/DELETE /products`, `POST /products/:id/image` |
| `/admin/pedidos` · `mensajes` · `usuarios` | admin | `GET /orders`, `GET /messages`, `GET /users`   |

Admin de prueba: `admin@tienda.com` / `Admin123!`

Si el token vence (401), la sesión se cierra y la app lleva al login.

## Estructura

```
src/
  api/client.js        axios con VITE_BASE_URL + token Bearer + 401 + mensajes de error
  api/index.js         funciones por endpoint
  context/             AuthContext (sesión) y CartContext (carrito en localStorage)
  components/          Navbar, Footer, ProtectedRoute, AuthLayout, AdminTabs, Icons
  pages/               una página por ruta
  utils/               URLs de imágenes, precios, pedidos, notificaciones
  index.css            diseño "Mosaico" (beige, carbón y rosa viejo)
```
