import { useEffect } from 'react'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import Footer from './components/Footer'
import Navbar from './components/Navbar'
import ProtectedRoute from './components/ProtectedRoute'
import AdminCategorias from './pages/AdminCategorias'
import AdminProductos from './pages/AdminProductos'
import AdminRegistros from './pages/AdminRegistros'
import Carrito from './pages/Carrito'
import Catalogo from './pages/Catalogo'
import Estado from './pages/Estado'
import Login from './pages/Login'
import Mensajes from './pages/Mensajes'
import Notificaciones from './pages/Notificaciones'
import Pago from './pages/Pago'
import Pedidos from './pages/Pedidos'
import ProductoDetalle from './pages/ProductoDetalle'
import Registro from './pages/Registro'

function NotFound() {
  return (
    <div className="page">
      <section className="empty">
        <span className="mono muted">Error 404</span>
        <h2>Página no encontrada</h2>
        <Link to="/" className="btn btn-dark">
          Ir al catálogo
        </Link>
      </section>
    </div>
  )
}

// Si el token vence (401), el cliente HTTP avisa y aquí se manda al login,
// recordando a qué página volver.
function SessionWatcher() {
  const navigate = useNavigate()
  const location = useLocation()

  useEffect(() => {
    const onExpired = () =>
      navigate('/login', { replace: true, state: { expired: true, from: location.pathname } })
    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [navigate, location.pathname])

  // Llaves obligatorias: en navegadores nuevos scrollTo devuelve una promesa,
  // y un efecto solo puede devolver una función de limpieza.
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [location.pathname])

  return null
}

const privada = (element, role) => <ProtectedRoute role={role}>{element}</ProtectedRoute>

export default function App() {
  return (
    <>
      <SessionWatcher />
      <Navbar />
      <main>
        <Routes>
          <Route path="/" element={<Catalogo />} />
          <Route path="/producto/:id" element={<ProductoDetalle />} />
          <Route path="/estado" element={<Estado />} />
          <Route path="/login" element={<Login />} />
          <Route path="/registro" element={<Registro />} />
          <Route path="/carrito" element={<Carrito />} />
          <Route path="/pago/:orderId" element={privada(<Pago />)} />
          <Route path="/pedidos" element={privada(<Pedidos />)} />
          <Route path="/notificaciones" element={privada(<Notificaciones />)} />
          <Route path="/mensajes" element={privada(<Mensajes />)} />
          <Route path="/mis-pedidos" element={<Navigate to="/pedidos" replace />} />
          <Route path="/admin/productos" element={privada(<AdminProductos />, 'admin')} />
          <Route path="/admin/categorias" element={privada(<AdminCategorias />, 'admin')} />
          <Route path="/admin/:section" element={privada(<AdminRegistros />, 'admin')} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </main>
      <Footer />
    </>
  )
}
