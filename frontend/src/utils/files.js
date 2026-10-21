import { BASE_URL } from '../api/client'

export const PLACEHOLDER_IMG = '/sin-imagen.svg'

// imageUrl viene del backend como "/files/abc123.jpg" (o null) y se pide
// siempre a través de NGINX: {BASE}/files/abc123.jpg.
// Si llegara una URL absoluta, se usa solo su ruta para no saltarse NGINX.
export function fileUrl(imageUrl) {
  if (!imageUrl) return PLACEHOLDER_IMG
  let path = imageUrl
  if (/^https?:\/\//.test(path)) {
    const url = new URL(path)
    path = url.pathname + url.search
  }
  if (!path.startsWith('/')) path = `/${path}`
  return `${BASE_URL}${path}`
}

export function formatPrice(value) {
  return `$ ${Number(value ?? 0).toFixed(2)}`
}

export function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' })
}

// Texto corto de disponibilidad, igual en catálogo, detalle y carrito.
export function stockLabel(stock) {
  if (stock <= 0) return 'Agotado'
  if (stock <= 3) return `Quedan ${stock}`
  return `${stock} disponibles`
}
