/**
 * Schemas de PostgreSQL en los que se organiza la base de datos.
 * Cada uno agrupa las tablas de un área de responsabilidad del negocio.
 */
export const DB_SCHEMAS = [
  'auth', // identidad y acceso: users
  'catalog', // productos que ofrece la tienda: products
  'sales', // operación comercial: orders, order_items, payments
  'storage', // metadatos de archivos guardados en L5: files
  'messaging', // comunicación con el cliente: notifications, messages
] as const;
