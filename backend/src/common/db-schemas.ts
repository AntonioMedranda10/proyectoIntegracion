/**
 * Schemas de PostgreSQL en los que se organiza la base de datos.
 * Cada uno agrupa las tablas de un área de responsabilidad del negocio.
 * La descripción se guarda como comentario y se ve en pgAdmin.
 */
export const DB_SCHEMAS = {
  auth: 'Identidad y acceso: usuarios, contraseñas cifradas y roles. PRIVADO.',
  catalog:
    'Catálogo de la tienda: categorías y productos. Lectura pública a través de la API.',
  sales:
    'Operación comercial: pedidos, detalle y pagos. PRIVADO, historial de ventas.',
  storage:
    'Metadatos de los archivos guardados en el servidor de archivos (L5). PRIVADO.',
  messaging: 'Comunicación con el cliente: notificaciones y mensajes. PRIVADO.',
  audit:
    'Auditoría: registro de operaciones que modifican datos. PRIVADO, solo inserción y consulta.',
} as const;

/** Comentario de cada tabla (schema.tabla -> descripción). */
export const DB_TABLE_COMMENTS: Record<string, string> = {
  'auth.users': 'Clientes y administradores de la tienda',
  'catalog.categories': 'Categorías que agrupan los productos',
  'catalog.products': 'Productos en venta, con precio, stock e imagen',
  'sales.orders': 'Pedidos realizados por los usuarios',
  'sales.order_items': 'Productos incluidos en cada pedido',
  'sales.payments': 'Pago de cada pedido (uno por pedido)',
  'storage.files': 'Registro de cada archivo almacenado en L5',
  'messaging.notifications': 'Avisos generados por el sistema para el usuario',
  'messaging.messages': 'Mensajes enviados por los clientes a la tienda',
  'audit.activity_log':
    'Quién modificó qué, cuándo y qué instancia del backend lo atendió',
};
