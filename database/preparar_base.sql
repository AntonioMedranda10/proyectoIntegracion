-- =====================================================================
-- Preparar un servidor PostgreSQL nuevo para el proyecto (nodo L4)
--
-- EJECUTAR COMO ADMINISTRADOR (usuario postgres) en el Query Tool de
-- pgAdmin, conectado a la base "postgres". Ejecutar las dos sentencias
-- UNA POR UNA (seleccionar cada una y presionar F5), porque CREATE
-- DATABASE no puede ir junto con otra sentencia.
--
-- NO hay que crear tablas ni schemas: el backend (L3) los crea solo
-- al arrancar (6 schemas, 10 tablas, el administrador y las categorías).
-- =====================================================================

-- 1. Usuario propio de la aplicación (no se usa el administrador postgres).
CREATE USER ecommerce_app WITH PASSWORD '123456';

-- 2. Base de datos vacía, propiedad de ese usuario.
--    Debe ser el dueño para poder crear los schemas y las tablas.
CREATE DATABASE "Proyecto_Integrador" OWNER ecommerce_app;

-- =====================================================================
-- DESPUÉS, fuera de pgAdmin:
--
-- A) Permitir la conexión del backend. Abrir como administrador el archivo
--      C:\Program Files\PostgreSQL\<versión>\data\pg_hba.conf
--    y agregar esta línea AL FINAL:
--
--      host    Proyecto_Integrador    ecommerce_app    192.168.137.0/24    scram-sha-256
--
-- B) En el mismo directorio, en postgresql.conf, comprobar que diga:
--
--      listen_addresses = '*'
--
-- C) Aplicar los cambios. En el Query Tool:
--
--      SELECT pg_reload_conf();
--
--    (Si se cambió listen_addresses hay que reiniciar el servicio:
--     services.msc -> postgresql-x64-<versión> -> Reiniciar.)
--
-- D) Permitir el puerto 5432 en el firewall de Windows
--    (PowerShell como administrador):
--
--      New-NetFirewallRule -DisplayName "PostgreSQL 5432" -Direction Inbound -Protocol TCP -LocalPort 5432 -Action Allow
--
-- E) Opcional, después de que el backend haya arrancado una vez:
--    ejecutar permisos.sql para crear el usuario de solo lectura.
-- =====================================================================
