import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { errorMessage } from '../api/client'
import AuthLayout from '../components/AuthLayout'
import { useAuth } from '../context/AuthContext'

export default function Registro() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ name: '', email: '', password: '' })
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await register({ ...form, name: form.name.trim(), email: form.email.trim() })
      navigate('/', { replace: true })
    } catch (err) {
      const status = err.response?.status
      const message = err.response?.data?.message
      setError({
        status,
        title: status === 409 ? 'Ese email ya está registrado' : status === 400 ? 'Revisa estos datos' : 'No pudimos crear tu cuenta',
        lines: status === 409 ? ['Si la cuenta es tuya, no necesitas crear otra.'] : Array.isArray(message) ? message : [errorMessage(err)],
      })
    } finally {
      setLoading(false)
    }
  }

  const shortPassword = form.password.length > 0 && form.password.length < 6

  return (
    <AuthLayout>
      <form className="form" onSubmit={onSubmit}>
        <div>
          <h1>Crea tu cuenta</h1>
          <p className="muted">Solo te tomará un minuto.</p>
        </div>
        {error && (
          <div className="alert alert-error" role="alert">
            <strong>{error.title}</strong>
            {error.lines.map((line) => (
              <div key={line}>{line}</div>
            ))}
            {error.status === 409 && (
              <Link to="/login" style={{ color: 'inherit', fontWeight: 700 }}>
                Iniciar sesión con ese email
              </Link>
            )}
          </div>
        )}
        <div className="field">
          <label htmlFor="reg-name">Nombre</label>
          <input id="reg-name" name="name" autoComplete="name" placeholder="Tu nombre y apellido" value={form.name} onChange={onChange} required />
        </div>
        <div className={`field ${error?.status === 409 ? 'invalid' : ''}`}>
          <label htmlFor="reg-email">Email</label>
          <input
            id="reg-email"
            name="email"
            type="email"
            autoComplete="email"
            placeholder="nombre@correo.com"
            value={form.email}
            onChange={onChange}
            required
          />
        </div>
        <div className={`field ${shortPassword ? 'invalid' : ''}`}>
          <label htmlFor="reg-password">Contraseña</label>
          <input
            id="reg-password"
            name="password"
            type="password"
            autoComplete="new-password"
            minLength={6}
            placeholder="Mínimo 6 caracteres"
            value={form.password}
            onChange={onChange}
            aria-describedby="reg-password-hint"
            required
          />
          <span className="field-hint" id="reg-password-hint">
            {shortPassword ? `Le faltan ${6 - form.password.length} caracteres: debe tener al menos 6` : 'Mínimo 6 caracteres'}
          </span>
        </div>
        <button className="btn btn-dark btn-block" disabled={loading}>
          {loading && <span className="spinner" aria-hidden="true" />}
          {loading ? 'Creando cuenta…' : 'Crear cuenta'}
        </button>
        <p className="muted">
          ¿Ya tienes cuenta? <Link to="/login">Inicia sesión</Link>
        </p>
      </form>
    </AuthLayout>
  )
}
