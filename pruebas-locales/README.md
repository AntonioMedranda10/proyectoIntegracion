# Pruebas locales del flujo "publicar producto con imagen"

Reproduce en **un solo PC** la topología L2 → L1 → L3 ×2 → L4 / L5, para probar la orquestación sin la red del aula.

| Nodo | En el aula | En este entorno local |
|---|---|---|
| L1 | NGINX | `l1-local.mjs` (puerto 8080, mismas reglas: `/api` round-robin, `/files` → L5) |
| L3 | backend ×2 | el mismo backend: `npm run start:instancias` (3001 y 3002) |
| L4 | PostgreSQL | `docker compose up -d` en `backend/` (puerto 5434) |
| L5 | servidor de archivos | `l5-simulado.mjs` (puerto 8090, contrato de `backend/README.md` §6 + `DELETE`) |
| L2 | frontend | `npm run dev` en `frontend/` |

Solo usan Node (sin dependencias). Los datos son ficticios y la clave de L5 es de prueba.

## 1. Configuración (una vez)

`backend/.env` (copiar de `.env.example`) con:

```
FILES_MODE=remote
FILES_URL=http://127.0.0.1:8090
FILES_API_KEY=<una clave de prueba>
FILES_TIMEOUT_MS=5000
```

`frontend/.env.development.local`:

```
NGINX_URL=http://127.0.0.1:8080
```

Los dos archivos están en `.gitignore`.

## 2. Arrancar (cinco terminales, desde la raíz del repositorio)

```bash
cd backend && docker compose up -d                                   # L4
L5_API_KEY=<la misma clave> node pruebas-locales/l5-simulado.mjs      # L5 (desde la raíz)
cd backend && npm run start:instancias                               # L3 ×2
node pruebas-locales/l1-local.mjs                                    # L1 (desde la raíz)
cd frontend && npm run dev                                           # L2 → http://localhost:5173
```

En PowerShell, la variable se pone antes: `$env:L5_API_KEY="<clave>"; node pruebas-locales/l5-simulado.mjs`.

Comprobación: `curl http://127.0.0.1:8080/api/health` alterna `backend-1` / `backend-2` con `database` y `files` en `up`.

## 3. Escenarios

| Escenario | Cómo provocarlo | Resultado esperado |
|---|---|---|
| Éxito | Todo encendido; Administración → Publicar producto con imagen | `completed`, imagen visible en el catálogo |
| Degradación (L5 caído) | Ctrl+C en la terminal de L5 y publicar | `degraded`, producto sin imagen y botón **Reintentar la imagen** |
| Degradación (L5 lento) | `L5_DEMORA_MS=8000` al arrancar L5 | `degraded` a los 5 s (`FILES_TIMEOUT_MS`) |
| Fallo de L4 + compensación | `docker stop ecommerce-postgres-dev` y publicar con imagen | `503 failed`; L5 registra `POST /upload 201` y `DELETE /files/... 200` |
| Recuperación | Volver a encender L5 / `docker start ecommerce-postgres-dev` | `/health` vuelve a `ok` sin reiniciar L3 |

Cada nodo escribe en su terminal el `correlation=...` de la petición: busca el mismo valor en L1, L3 y L5.
