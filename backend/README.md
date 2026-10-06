# Backend L3 – E-commerce (NestJS + TypeORM)

API REST del nodo **L3** de la arquitectura de 5 nodos. Corre en **dos instancias** (puertos 3001 y 3002) detrás de NGINX (L1), guarda datos en PostgreSQL (L4) y archivos en el servidor de archivos (L5).

El contrato de endpoints acordado con el frontend está en [`../Plan_Frontend_Contrato_API.md`](../Plan_Frontend_Contrato_API.md).
Documentación interactiva (Swagger): `http://localhost:3001/api/docs`

---

## 1. Requisitos
- Node.js 20 o superior
- Docker Desktop (solo para la base de datos de desarrollo)

## 2. Primera vez
```bash
npm install
cp .env.example .env      # en Windows también puedes copiarlo a mano
npm run db:up             # levanta PostgreSQL de desarrollo en el puerto 5434
```

## 3. Ejecutar

| Comando | Para qué |
|---|---|
| `npm run start:dev` | Una instancia (3001) con recarga automática. Para programar. |
| `npm run start:instancias` | Compila y levanta **las dos instancias** (backend-1 :3001 y backend-2 :3002). Para la prueba. |
| `npm run db:up` / `npm run db:down` | Encender / apagar el PostgreSQL de desarrollo. |

Usuario administrador creado automáticamente: **admin@tienda.com / Admin123!**

Comprobación rápida:
```bash
curl http://localhost:3001/api/health
curl http://localhost:3002/api/health
```

## 4. Configuración (`.env`)

| Variable | Descripción |
|---|---|
| `PORT`, `INSTANCE_NAME` | Puerto y nombre de la instancia (`start:instancias` los fija solos). |
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` | Conexión a PostgreSQL (L4). |
| `DB_SYNC` | `true` = crea las tablas y el admin al arrancar. Solo backend-1 lo hace. |
| `JWT_SECRET` | Secreto de los tokens. **Debe ser igual en ambas instancias.** |
| `FILES_MODE` | `local` = guarda imágenes en `./uploads` (sin L5) · `remote` = las envía a L5. |
| `FILES_URL` | URL del servidor de archivos, ej. `http://192.168.10.15`. |
| `FILES_UPLOAD_PATH` | Ruta de subida en L5 (por defecto `/upload`). |
| `FILES_API_KEY` | Clave que L5 exige en la cabecera `x-api-key`. Vacía = no se envía. |

## 5. Día de la prueba (checklist)

1. **Base de datos real (L4):** en `.env` cambia `DB_HOST` por la IP de L4, `DB_PORT=5432` y las credenciales que te dé tu compañero. L4 debe permitir conexiones desde tu IP (`pg_hba.conf` y `listen_addresses`).
2. **Servidor de archivos (L5):** `FILES_MODE=remote` y `FILES_URL=http://<IP de L5>`. Si L5 aún no está listo, deja `FILES_MODE=local`.
3. **Firewall de Windows:** abre los puertos una sola vez (PowerShell como administrador):
   ```powershell
   New-NetFirewallRule -DisplayName "Backend NestJS 3001-3002" -Direction Inbound -Protocol TCP -LocalPort 3001,3002 -Action Allow
   ```
4. **Tu IP:** ejecuta `ipconfig` y pásale a L1 (NGINX) tu IPv4 (Wi-Fi o Radmin VPN, según cómo se conecte el equipo).
5. **NGINX (L1)** debe tener algo así (conservando el prefijo `/api`):
   ```nginx
   upstream backend {
       server <TU_IP>:3001;
       server <TU_IP>:3002;
   }
   location /api/ {
       proxy_pass http://backend;          # SIN barra final: conserva /api
       client_max_body_size 5M;            # para subir imágenes
   }
   ```
6. Ejecuta `npm run start:instancias` y abre `http://<IP de L1>/api/health` varias veces: debe alternar entre `backend-1` y `backend-2`.

## 6. Contrato esperado con L5 (servidor de archivos)
- `POST {FILES_URL}/upload` con `multipart/form-data`, campo **`file`** y cabecera **`x-api-key`** (valor de `FILES_API_KEY`).
- Respuesta JSON: `{ "storage_key": "abc.png", "url": "/files/abc.png" }` (también acepta `key` o `filename`; si falta `url` se usa `/files/<clave>`).
- L1 debe enrutar `/files/` hacia L5 para que el navegador vea las imágenes.

## 7. Estructura

```
src/
  main.ts            prefijo /api, validación, CORS, Swagger, header X-Backend-Instance
  app.module.ts      conexión a PostgreSQL y registro de los 10 módulos
  common/            guards de JWT y roles, decorador @Auth(), @CurrentUser()
  auth/              registro, login, /me (JWT)
  users/             entidad User, admin inicial, listado (admin)
  products/          CRUD de productos + subida de imagen (admin)
  catalog/           lectura pública del catálogo
  files/             envío de archivos a L5 y metadatos en la tabla files
  orders/            pedidos (descuenta stock con bloqueo de fila)
  payments/          pago simulado de pedidos
  notifications/     notificaciones por usuario
  messages/          mensajes de clientes a la tienda
  health/            estado de la instancia, L4 y L5
```

Flujo de una petición: `Guards (JWT/rol) → ValidationPipe (DTO) → Controller → Service → Repository (TypeORM) → PostgreSQL`.
