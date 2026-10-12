-- =====================================================================
-- Permisos por schema: qué es PRIVADO y qué es de LECTURA PÚBLICA
-- Base: Proyecto_Integrador
--
-- EJECUTAR COMO ADMINISTRADOR (usuario postgres), conectado a la base
-- Proyecto_Integrador, DESPUÉS de que el backend haya creado los schemas.
-- Se puede ejecutar varias veces sin problema.
--
-- Resultado:
--   ecommerce_app      -> dueño de todo; es el usuario que usa el backend (L3).
--   ecommerce_consulta -> solo puede LEER el schema catalog (el catálogo es
--                         la única información pública de la tienda).
--   Nadie más puede entrar a auth, sales, storage, messaging ni audit.
-- =====================================================================

-- 1. Usuario de solo lectura (para reportes o consultas externas).
--    Cambiar la contraseña antes de usarlo en un entorno real.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ecommerce_consulta') THEN
        CREATE ROLE ecommerce_consulta LOGIN PASSWORD 'consulta123';
    END IF;
END
$$;

-- 2. Nadie entra a la base ni a public por defecto.
REVOKE ALL ON DATABASE "Proyecto_Integrador" FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT CONNECT ON DATABASE "Proyecto_Integrador" TO ecommerce_consulta;

-- 3. Schemas PRIVADOS: solo ecommerce_app (su dueño).
REVOKE ALL ON SCHEMA auth, sales, storage, messaging, audit FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA auth, sales, storage, messaging, audit FROM PUBLIC;

-- 4. Schema de LECTURA PÚBLICA: el catálogo.
GRANT USAGE ON SCHEMA catalog TO ecommerce_consulta;
GRANT SELECT ON ALL TABLES IN SCHEMA catalog TO ecommerce_consulta;
-- Las tablas que el backend cree después en catalog también serán legibles.
ALTER DEFAULT PRIVILEGES FOR ROLE ecommerce_app IN SCHEMA catalog
    GRANT SELECT ON TABLES TO ecommerce_consulta;

-- =====================================================================
-- Cómo comprobarlo (conectándose como ecommerce_consulta):
--   SELECT * FROM catalog.products;   -- funciona
--   SELECT * FROM auth.users;         -- ERROR: permiso denegado al schema auth
--   INSERT INTO catalog.products ...  -- ERROR: permiso denegado
-- =====================================================================
