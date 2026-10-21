import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // NGINX_URL: dirección de NGINX (L1). En `npm run dev` y `npm run preview`
  // Vite reenvía /api y /files ahí, así el navegador usa rutas relativas y no
  // hace falta CORS (da igual si se entra por localhost, por la IP de este PC
  // o a través del propio NGINX).
  const nginx = loadEnv(mode, process.cwd(), '').NGINX_URL?.trim().replace(/\/+$/, '')

  const toNginx = {
    target: nginx,
    changeOrigin: true,
    // Si NGINX no responde, devolver un mensaje claro en vez de un 502 vacío.
    configure: (proxy) =>
      proxy.on('error', (_err, _req, res) => {
        if (!res.writeHead || res.headersSent) return
        res.writeHead(502, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ message: `No se pudo conectar con NGINX en ${nginx}. Revisa la red y NGINX_URL en el .env.` }))
      }),
  }
  const proxy = nginx ? { '/api': toNginx, '/files': toNginx } : undefined

  return {
    plugins: [react()],
    server: {
      host: true, // accesible desde otras PCs de la red
      port: 5173,
      // NGINX (L1) puede reenviar aquí con cualquier Host (IP o nombre del upstream).
      allowedHosts: true,
      proxy,
    },
    preview: {
      host: true,
      port: 4173,
      allowedHosts: true,
      proxy,
    },
  }
})
