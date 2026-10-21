import axios from 'axios'

// Única dirección del sistema: NGINX (L1). Todo sale de aquí:
//   API      -> {BASE}/api/...
//   Imágenes -> {BASE}/files/...
// Si VITE_BASE_URL está vacía se usan rutas relativas (/api, /files),
// que es lo correcto cuando el frontend lo sirve el mismo NGINX.
// También se acepta VITE_API_URL (http://<IP>/api), que es el nombre que usa el
// documento del contrato: se le quita el /api final para obtener la base.
const env = import.meta.env
export const BASE_URL = (env.VITE_BASE_URL || (env.VITE_API_URL ?? '').replace(/\/api\/?$/, ''))
  .trim()
  .replace(/\/+$/, '')
export const API_URL = `${BASE_URL}/api`

const TOKEN_KEY = 'access_token'
const USER_KEY = 'user'
export const EXPIRED_KEY = 'session_expired'

export const session = {
  getToken: () => localStorage.getItem(TOKEN_KEY),
  getUser: () => {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY))
    } catch {
      return null
    }
  },
  save: (token, user) => {
    localStorage.setItem(TOKEN_KEY, token)
    localStorage.setItem(USER_KEY, JSON.stringify(user))
  },
  clear: () => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  },
}

export const api = axios.create({
  baseURL: API_URL,
  timeout: 10000,
})

api.interceptors.request.use((config) => {
  const token = session.getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// Última instancia del backend que respondió (header X-Backend-Instance).
// Sirve para mostrar el balanceo de NGINX en el pie de página y en /estado.
export let lastInstance = null

function rememberInstance(headers) {
  const instance = headers?.['x-backend-instance']
  if (!instance) return
  lastInstance = instance
  window.dispatchEvent(new CustomEvent('backend:instance', { detail: instance }))
}

// 401 en una petición que llevaba token = sesión vencida o inválida:
// se cierra la sesión y la app redirige al login (ver SessionWatcher en App.jsx).
// Un 401 sin token (p. ej. credenciales incorrectas en el login) se deja pasar.
api.interceptors.response.use(
  (res) => {
    // Si /api devuelve una página HTML, la dirección base no apunta a NGINX
    // (p. ej. falta NGINX_URL en `npm run dev`): mejor avisar que fallar raro.
    if (String(res.headers['content-type'] ?? '').includes('text/html')) {
      return Promise.reject(
        Object.assign(new Error('html'), {
          response: {
            status: 502,
            data: { message: `La API no respondió en ${API_URL}. Revisa NGINX_URL y VITE_BASE_URL en el archivo .env.` },
          },
        }),
      )
    }
    rememberInstance(res.headers)
    return res
  },
  (error) => {
    rememberInstance(error.response?.headers)
    const sentToken = Boolean(error.config?.headers?.Authorization)
    if (error.response?.status === 401 && sentToken) {
      session.clear()
      // Marca para que el login muestre "Tu sesión expiró" aunque otra
      // redirección (p. ej. la de una ruta protegida) llegue primero.
      sessionStorage.setItem(EXPIRED_KEY, '1')
      window.dispatchEvent(new Event('auth:expired'))
    }
    return Promise.reject(error)
  },
)

// Convierte el formato de error del backend en un texto para mostrar.
// { statusCode, message: string | string[], error }
export function errorMessage(error) {
  if (!error.response) {
    return 'No se pudo conectar con el servidor. Revisa que NGINX y el backend estén encendidos.'
  }
  const { status, data } = error.response
  const message = data?.message
  if (Array.isArray(message) && message.length) return message.join('. ')
  if (typeof message === 'string' && message) return message
  if (status === 401) return 'Tu sesión no es válida. Inicia sesión de nuevo.'
  if (status === 403) return 'No tienes permiso para hacer esto.'
  if (status === 404) return 'No se encontró lo que buscabas.'
  if (status === 413) return 'El archivo es demasiado grande.'
  return `Error ${status}`
}
