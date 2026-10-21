# Proyecto Integración – E-commerce en arquitectura de 5 nodos

| Nodo | Componente | Responsable | Carpeta |
|---|---|---|---|
| L1 | NGINX / balanceador | — | *(pendiente)* |
| L2 | Frontend React/Vite | — | [`frontend/`](frontend/) |
| L3 | Backend NestJS ×2 (puertos 3001/3002) | Antonio Medranda | [`backend/`](backend/) |
| L4 | PostgreSQL | — | *(pendiente)* |
| L5 | Servidor de archivos | — | *(pendiente)* |

## Documentos
- [Documento de arquitectura (Word)](Documento_detallado_arquitectura_5_nodos.docx): descripción de los 5 nodos y su comunicación.
- [Plan Frontend + Contrato de API](Plan_Frontend_Contrato_API.md): roles, pantallas y endpoints acordados entre frontend y backend.
- [README del backend](backend/README.md): cómo ejecutar L3 y la lista de pasos para la prueba.
- [README del frontend](frontend/README.md): cómo ejecutar L2, configuración de NGINX_URL y pantallas.
- [Schema de la base de datos](database/schema.sql): las 10 tablas en 6 schemas que crea el backend en PostgreSQL (referencia para L4).
- [Permisos por schema](database/permisos.sql): qué schemas son privados y cuál es de lectura pública (lo ejecuta L4 como administrador).

## Ramas
- **`main`**: versión estable y probada (la que se presenta).
- **`develop`**: integración del trabajo en curso. Los cambios se hacen aquí (o en ramas `feature/...` que salen de `develop`) y pasan a `main` cuando están probados.

## Flujo de comunicación
```
Navegador ──► L1 NGINX ──┬──► L2 Frontend (/)
                         ├──► L3 Backend ×2 (/api) ──┬──► L4 PostgreSQL
                         │                          └──► L5 Archivos
                         └──► L5 Archivos (/files)
```
