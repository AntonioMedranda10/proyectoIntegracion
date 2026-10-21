import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { errorMessage, EXPIRED_KEY } from '../api/client'
import AuthLayout from '../components/AuthLayout'
import { useAuth } from '../context/AuthContext'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [expired] = useState(() => sessionStorage.getItem(EXPIRED_KEY) === '1')

  // La marca de "sesión expirada" se usa una sola vez.
  useEffect(() => {
    sessionStorage.removeItem(EXPIRED_KEY)
  }, [])

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const user = await login(form)
      const fallback = user.role === 'admin' ? '/admin/productos' : '/'
      navigate(location.state?.from ?? fallback, { replace: true })
    } catch (err) {
      setError(
        err.response?.status === 401
          ? 'El email o la contraseña no son correctos. Revísalos e inténtalo de nuevo.'
          : errorMessage(err),
      )
    } finally {
      setLoading(false)
    }
  }

  const reason = expired || location.state?.expired
    ? 'Tu sesión expiró. Inicia sesión de nuevo para continuar.'
    : location.state?.reason === 'cart'
      ? 'Inicia sesión para agregar productos al carrito.'
      : location.state?.from
        ? 'Inicia sesión para ver esa página.'
        : ''

  return (
    <AuthLayout>
      <form className="form" onSubmit={onSubmit}>
        <div>
          <h1>Inicia sesión</h1>
          <p className="muted">Entra para comprar y ver tus pedidos.</p>
        </div>
        {reason && <div className="alert alert-info">{reason}</div>}
        {error && (
          <div className="alert alert-error" role="alert">
            <strong>No pudimos iniciar tu sesión</strong>
            {error}
          </div>
        )}
        <div className="field">
          <label htmlFor="login-email">Email</label>
          <input
            id="login-email"
            name="email"
            type="email"
            autoComplete="email"
            placeholder="nombre@correo.com"
            value={form.email}
            onChange={onChange}
            required
          />
        </div>
        <div className={`field ${error ? 'invalid' : ''}`}>
          <label htmlFor="login-password">Contraseña</label>
          <input
            id="login-password"
            name="password"
            type="password"
            autoComplete="current-password"
            placeholder="Tu contraseña"
            value={form.password}
            onChange={onChange}
            required
          />
        </div>
        <button className="btn btn-dark btn-block" disabled={loading}>
          {loading && <span className="spinner" aria-hidden="true" />}
          {loading ? 'Ingresando…' : 'Ingresar'}
        </button>
        <p className="muted">
          ¿Todavía no tienes cuenta? <Link to="/registro">Regístrate</Link>
        </p>
      </form>
    </AuthLayout>
  )
}
