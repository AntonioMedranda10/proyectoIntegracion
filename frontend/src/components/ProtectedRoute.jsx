import { Link, Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

export default function ProtectedRoute({ role, children }) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />
  if (role && user.role !== role) {
    return (
      <div className="page">
        <section className="empty" role="alert">
          <span className="mono muted">Error 403</span>
          <h2>Esta sección es solo para administradores</h2>
          <p>Tu cuenta es de cliente, así que no puedes entrar aquí.</p>
          <Link to="/" className="btn btn-dark">
            Ir al catálogo
          </Link>
        </section>
      </div>
    )
  }
  return children
}
