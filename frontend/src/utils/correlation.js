// X-Correlation-ID: identifica una operación de punta a punta (L2 → L1 → L3 → L5).
// Lo genera el navegador para poder buscar la misma operación en los logs de cada nodo.
// crypto.randomUUID solo existe en contextos seguros (https o localhost); en la red del
// aula se entra por http://<IP de L1>, así que se arma un UUID v4 con getRandomValues.
export function newCorrelationId() {
  if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID()
  const b = globalThis.crypto.getRandomValues(new Uint8Array(16))
  b[6] = (b[6] & 0x0f) | 0x40
  b[8] = (b[8] & 0x3f) | 0x80
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('')
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}
