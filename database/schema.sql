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
-- 3. catalog: productos (módulos products / catalog)
-- ---------------------------------------------------------------------
CREATE TABLE catalog.products (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(120)  NOT NULL,
    description TEXT,
    price       NUMERIC(10,2) NOT NULL,
    stock       INTEGER       NOT NULL DEFAULT 0,
    image_url   VARCHAR,                                    -- URL de la imagen en L5
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
