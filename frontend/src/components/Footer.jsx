import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { healthApi } from '../api'
import { useAuth } from '../context/AuthContext'

const STATES = {
  ok: { label: 'Sistema operativo', cls: 'ok' },
  degraded: { label: 'Sistema degradado', cls: 'warn' },
  down: { label: 'Sin conexión', cls: 'err' },
}

// Consulta /api/health al cargar y cada minuto; también se actualiza con
// cada respuesta de la API (el cliente HTTP avisa qué instancia respondió).
function SystemStatus() {
  const [state, setState] = useState(null)
  const [instance, setInstance] = useState(null)

  useEffect(() => {
    let active = true
    const check = () =>
      healthApi
        .check()
        .then((h) => {
          if (!active) return
          setState(h.status === 'ok' ? 'ok' : 'degraded')
          setInstance(h.instance ?? h.headerInstance)
        })
        .catch(() => active && setState('down'))
    check()
    const timer = setInterval(check, 60000)
    const onInstance = (e) => setInstance(e.detail)
    window.addEventListener('backend:instance', onInstance)
    return () => {
      active = false
      clearInterval(timer)
      window.removeEventListener('backend:instance', onInstance)
    }
  }, [])

  if (!state) return null
  const s = STATES[state]
  return (
    <Link to="/estado" className={`footer-status ${s.cls}`} title="Ver el estado del sistema">
      <span className="dot" aria-hidden="true" />
      {s.label}
      {instance && state !== 'down' && <span className="mono"> · {instance}</span>}
    </Link>
  )
}

export default function Footer() {
  const { user } = useAuth()
  const year = new Date().getFullYear()

  return (
    <footer className="site-footer">
      <div className="container footer-main">
        <span className="footer-brand">
          <span className="brand-dot" aria-hidden="true" />
          Tienda 5 Nodos
        </span>
        <nav aria-label="Pie de página" className="footer-links">
          <Link to="/">Catálogo</Link>
          {user && <Link to="/pedidos">Mis pedidos</Link>}
          <Link to="/mensajes">Escríbenos</Link>
          <Link to="/estado">Estado del sistema</Link>
        </nav>
        <SystemStatus />
      </div>
      <div className="container footer-legal">
        <span>© {year} Tienda 5 Nodos · Proyecto universitario: NGINX, 2 backends, base de datos y archivos</span>
        <span>Pagos con tarjeta, efectivo o transferencia</span>
      </div>
    </footer>
  )
}
