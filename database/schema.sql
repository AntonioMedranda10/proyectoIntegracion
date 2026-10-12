-- =====================================================================
-- Modelo de datos por schemas - E-commerce (arquitectura de 5 nodos)
-- Motor: PostgreSQL 15+     Base: Proyecto_Integrador     Usuario: ecommerce_app
--
-- El backend (L3) crea esta misma estructura al arrancar (backend-1, DB_SYNC=true):
-- primero los schemas y luego las tablas (TypeORM). Este script sirve como
-- referencia y para crearla a mano: ejecutarlo con el usuario ecommerce_app,
-- sobre una base vacía y ANTES de iniciar el backend.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Schemas: uno por área de responsabilidad
-- ---------------------------------------------------------------------
CREATE SCHEMA IF NOT EXISTS auth;       -- identidad y acceso
CREATE SCHEMA IF NOT EXISTS catalog;    -- productos que ofrece la tienda
CREATE SCHEMA IF NOT EXISTS sales;      -- operación comercial: pedidos y pagos
CREATE SCHEMA IF NOT EXISTS storage;    -- metadatos de archivos guardados en L5
CREATE SCHEMA IF NOT EXISTS messaging;  -- comunicación con el cliente
CREATE SCHEMA IF NOT EXISTS audit;      -- registro de operaciones que modifican datos

-- ---------------------------------------------------------------------
-- 2. auth: usuarios (módulos auth / users)
-- ---------------------------------------------------------------------
CREATE TABLE auth.users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR      NOT NULL,                   -- bcrypt
    role          VARCHAR(20)  NOT NULL DEFAULT 'customer', -- 'customer' | 'admin'
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 3. catalog: categorías y productos (módulos products / catalog)
-- ---------------------------------------------------------------------
CREATE TABLE catalog.categories (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(80) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE catalog.products (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    description TEXT,
    price       NUMERIC(10,2) NOT NULL,
    stock       INTEGER       NOT NULL DEFAULT 0,
    image_url   VARCHAR,                                    -- URL de la imagen en L5
    category_id INTEGER
                REFERENCES catalog.categories(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 4. sales: pedidos, detalle y pagos (módulos orders / payments)
-- ---------------------------------------------------------------------
CREATE TABLE sales.orders (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER       NOT NULL
               REFERENCES auth.users(id) ON DELETE RESTRICT,
    status     VARCHAR(20)   NOT NULL DEFAULT 'pending',    -- 'pending' | 'paid' | 'cancelled'
    total      NUMERIC(12,2) NOT NULL,
    created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE sales.order_items (
    id           SERIAL PRIMARY KEY,
    order_id     INTEGER NOT NULL
                 REFERENCES sales.orders(id) ON DELETE CASCADE,
    product_id   INTEGER
                 REFERENCES catalog.products(id) ON DELETE SET NULL,
    product_name VARCHAR       NOT NULL,                    -- copia al momento de comprar
    unit_price   NUMERIC(10,2) NOT NULL,                    -- copia al momento de comprar
    quantity     INTEGER       NOT NULL
);

CREATE TABLE sales.payments (
    id         SERIAL PRIMARY KEY,
    order_id   INTEGER       NOT NULL UNIQUE                -- un pago por pedido
               REFERENCES sales.orders(id) ON DELETE RESTRICT,
    user_id    INTEGER       NOT NULL
               REFERENCES auth.users(id) ON DELETE RESTRICT,
    amount     NUMERIC(12,2) NOT NULL,
    method     VARCHAR(20)   NOT NULL,                      -- 'card' | 'cash' | 'transfer'
    status     VARCHAR(20)   NOT NULL DEFAULT 'approved',
    created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 5. storage: metadatos de archivos en L5 (módulo files)
-- ---------------------------------------------------------------------
CREATE TABLE storage.files (
    id            SERIAL PRIMARY KEY,
    storage_key   VARCHAR     NOT NULL,                     -- clave que devuelve L5
    url           VARCHAR     NOT NULL,
    original_name VARCHAR     NOT NULL,
    mime_type     VARCHAR     NOT NULL,
    size          INTEGER     NOT NULL,                     -- bytes
    owner_type    VARCHAR(30) NOT NULL,                     -- ej. 'product' (referencia genérica)
    owner_id      INTEGER     NOT NULL,
    uploaded_by   INTEGER     NOT NULL
                  REFERENCES auth.users(id) ON DELETE RESTRICT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 6. messaging: notificaciones y mensajes (módulos notifications / messages)
-- ---------------------------------------------------------------------
CREATE TABLE messaging.notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER     NOT NULL
               REFERENCES auth.users(id) ON DELETE CASCADE,
    message    VARCHAR     NOT NULL,
    read       BOOLEAN     NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE messaging.messages (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER      NOT NULL
               REFERENCES auth.users(id) ON DELETE CASCADE,
    subject    VARCHAR(150) NOT NULL,
    content    TEXT         NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 7. audit: registro de actividad (interceptor global del backend)
-- ---------------------------------------------------------------------
CREATE TABLE audit.activity_log (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER                                     -- NULL en registro/login
                REFERENCES auth.users(id) ON DELETE SET NULL,
    method      VARCHAR(10)  NOT NULL,                      -- POST, PATCH, DELETE
    path        VARCHAR(255) NOT NULL,
    status_code INTEGER      NOT NULL,
    instance    VARCHAR(50)  NOT NULL,                      -- backend-1 | backend-2
    ip_address  VARCHAR(64),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX ON audit.activity_log (created_at);

-- ---------------------------------------------------------------------
-- 8. Descripciones (se ven en pgAdmin, en las propiedades de cada objeto)
-- ---------------------------------------------------------------------
COMMENT ON SCHEMA auth      IS 'Identidad y acceso: usuarios, contraseñas cifradas y roles. PRIVADO.';
COMMENT ON SCHEMA catalog   IS 'Catálogo de la tienda: categorías y productos. Lectura pública a través de la API.';
COMMENT ON SCHEMA sales     IS 'Operación comercial: pedidos, detalle y pagos. PRIVADO, historial de ventas.';
COMMENT ON SCHEMA storage   IS 'Metadatos de los archivos guardados en el servidor de archivos (L5). PRIVADO.';
COMMENT ON SCHEMA messaging IS 'Comunicación con el cliente: notificaciones y mensajes. PRIVADO.';
COMMENT ON SCHEMA audit     IS 'Auditoría: registro de operaciones que modifican datos. PRIVADO, solo inserción y consulta.';
COMMENT ON SCHEMA public    IS 'Sin uso. Las tablas del proyecto están organizadas en schemas por responsabilidad.';

COMMENT ON TABLE auth.users              IS 'Clientes y administradores de la tienda';
COMMENT ON TABLE catalog.categories      IS 'Categorías que agrupan los productos';
COMMENT ON TABLE catalog.products        IS 'Productos en venta, con precio, stock e imagen';
COMMENT ON TABLE sales.orders            IS 'Pedidos realizados por los usuarios';
COMMENT ON TABLE sales.order_items       IS 'Productos incluidos en cada pedido';
COMMENT ON TABLE sales.payments          IS 'Pago de cada pedido (uno por pedido)';
COMMENT ON TABLE storage.files           IS 'Registro de cada archivo almacenado en L5';
COMMENT ON TABLE messaging.notifications IS 'Avisos generados por el sistema para el usuario';
COMMENT ON TABLE messaging.messages      IS 'Mensajes enviados por los clientes a la tienda';
COMMENT ON TABLE audit.activity_log      IS 'Quién modificó qué, cuándo y qué instancia del backend lo atendió';

-- Los permisos (qué schema es privado y cuál de lectura pública) están en permisos.sql.
