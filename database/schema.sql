-- =====================================================================
-- Schema de la base de datos - E-commerce (arquitectura de 5 nodos)
-- Motor: PostgreSQL 15+     Base: Proyecto_Integrador
--
-- IMPORTANTE: este archivo es de REFERENCIA. El backend (L3) crea estas
-- tablas automáticamente al arrancar (TypeORM, DB_SYNC=true en backend-1).
-- No es necesario ejecutarlo. Si se ejecuta a mano, hacerlo con el usuario
-- ecommerce_app y ANTES de iniciar el backend.
-- =====================================================================

-- ---------------------------------------------------------------------
-- users: clientes y administradores (módulos auth / users)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR      NOT NULL,             -- contraseña cifrada con bcrypt
    role          VARCHAR(20)  NOT NULL DEFAULT 'customer',  -- 'customer' | 'admin'
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- products: catálogo de la tienda (módulos products / catalog)
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(120)   NOT NULL,
    description TEXT,
    price       NUMERIC(10,2)  NOT NULL,
    stock       INTEGER        NOT NULL DEFAULT 0,
    image_url   VARCHAR,                             -- URL de la imagen en L5
    created_at  TIMESTAMPTZ    NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ    NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- files: metadatos de los archivos guardados en L5 (módulo files)
-- ---------------------------------------------------------------------
CREATE TABLE files (
    id            SERIAL PRIMARY KEY,
    storage_key   VARCHAR      NOT NULL,             -- clave que devuelve L5
    url           VARCHAR      NOT NULL,
    original_name VARCHAR      NOT NULL,
    mime_type     VARCHAR      NOT NULL,
    size          INTEGER      NOT NULL,             -- bytes
    owner_type    VARCHAR(30)  NOT NULL,             -- ej. 'product'
    owner_id      INTEGER      NOT NULL,             -- id del objeto dueño
    uploaded_by   INTEGER      NOT NULL,             -- id del usuario que subió
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- orders + order_items: pedidos y su detalle (módulo orders)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER        NOT NULL,
    status     VARCHAR(20)    NOT NULL DEFAULT 'pending',  -- 'pending' | 'paid' | 'cancelled'
    total      NUMERIC(12,2)  NOT NULL,
    created_at TIMESTAMPTZ    NOT NULL DEFAULT now()
);

CREATE TABLE order_items (
    id           SERIAL PRIMARY KEY,
    order_id     INTEGER REFERENCES orders(id)   ON DELETE CASCADE,
    product_id   INTEGER REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR        NOT NULL,           -- copia del nombre al comprar
    unit_price   NUMERIC(10,2)  NOT NULL,           -- copia del precio al comprar
    quantity     INTEGER        NOT NULL
);

-- ---------------------------------------------------------------------
-- payments: pagos simulados de pedidos (módulo payments)
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id         SERIAL PRIMARY KEY,
    order_id   INTEGER        NOT NULL,
    user_id    INTEGER        NOT NULL,
    amount     NUMERIC(12,2)  NOT NULL,
    method     VARCHAR(20)    NOT NULL,             -- 'card' | 'cash' | 'transfer'
    status     VARCHAR(20)    NOT NULL DEFAULT 'approved',
    created_at TIMESTAMPTZ    NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- notifications: avisos para el usuario (módulo notifications)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER      NOT NULL,
    message    VARCHAR      NOT NULL,
    read       BOOLEAN      NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- messages: mensajes de clientes a la tienda (módulo messages)
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER       NOT NULL,
    subject    VARCHAR(150)  NOT NULL,
    content    TEXT          NOT NULL,
    created_at TIMESTAMPTZ   NOT NULL DEFAULT now()
);
