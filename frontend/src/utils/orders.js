// Forma real (Swagger): Order { id, userId, status: pending|paid|cancelled, total, items, createdAt }
// OrderItem { productId, productName, unitPrice, quantity } · Payment { orderId, amount, method, status }

export const METHODS = {
  card: { label: 'Tarjeta', hint: 'Débito o crédito' },
  cash: { label: 'Efectivo', hint: 'Pagas al recibir el pedido' },
  transfer: { label: 'Transferencia', hint: 'Desde tu banco' },
}

export const STATUS = {
  pending: { label: 'Pendiente de pago', tag: 'tag-soft' },
  paid: { label: 'Pagado', tag: 'tag-ok' },
  cancelled: { label: 'Cancelado', tag: 'tag-err' },
}

export const itemName = (it) => it.productName ?? it.product?.name ?? `Producto n.º ${it.productId}`

export const itemPrice = (it) => Number(it.unitPrice ?? it.product?.price ?? 0)

export const orderItems = (o) => o?.items ?? []

export function orderTotal(o) {
  if (o?.total != null) return Number(o.total)
  return orderItems(o).reduce((sum, it) => sum + itemPrice(it) * Number(it.quantity ?? 1), 0)
}

export function orderSummary(o) {
  const items = orderItems(o)
  const units = items.reduce((sum, it) => sum + Number(it.quantity ?? 1), 0)
  const names = items.map((it) => `${it.quantity ?? 1} × ${itemName(it)}`).join(', ')
  return { units, names }
}
