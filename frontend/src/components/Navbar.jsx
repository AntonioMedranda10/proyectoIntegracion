import { useEffect, useState } from 'react'
import { Link, NavLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { notificationsApi } from '../api'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { isRead } from '../utils/notifications'
import { IconBell, IconCart, IconSearch } from './Icons'

const initials = (name = '') =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')

function SearchBox() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const current = params.get('search') ?? ''
  const [term, setTerm] = useState(current)

  // Si la búsqueda cambia desde otro lado (p. ej. "Ver todo"), se refleja aquí.
  useEffect(() => {
    setTerm(current)
  }, [current])

  const onSubmit = (e) => {
    e.preventDefault()
    const q = term.trim()
    navigate(q ? `/?search=${encodeURIComponent(q)}` : '/')
  }

  return (
    <form role="search" className="search-pill" onSubmit={onSubmit}>
      <label htmlFor="nav-search" className="sr-only">
        Buscar producto
      </label>
      <IconSearch size={18} />
      <input
        id="nav-search"
        type="search"
        placeholder="Buscar un producto"
        value={term}
        onChange={(e) => setTerm(e.target.value)}
      />
      <button className="btn btn-dark btn-sm">Buscar</button>
    </form>
  )
}

export default function Navbar() {
  const { user, isAdmin, logout } = useAuth()
  const { count } = useCart()
  const navigate = useNavigate()
  const location = useLocation()
  const [unread, setUnread] = useState(0)

  // Avisos sin leer: se consultan al iniciar sesión y al cambiar de página.
  useEffect(() => {
    if (!user) return
    let active = true
    notificationsApi
      .list()
      .then((list) => active && setUnread(list.filter((n) => !isRead(n)).length))
      .catch(() => active && setUnread(0))
    return () => {
      active = false
    }
  }, [user, location.pathname])

  const handleLogout = () => {
    logout()
    navigate('/')
  }

  return (
    <header className="site-header">
      <Link to="/" className="brand">
        <span className="brand-dot" aria-hidden="true" />
        Tienda 5 Nodos
      </Link>
      <SearchBox />
      <nav aria-label="Principal" className="nav">
        <NavLink to="/estado" className="nav-link">
          Estado del sistema
        </NavLink>
        {isAdmin && (
          <NavLink to="/admin/productos" className="nav-link">
            Administración
          </NavLink>
        )}
        {user ? (
          <>
            <NavLink to="/pedidos" className="nav-link">
              Mis pedidos
            </NavLink>
            <Link
              to="/notificaciones"
              className="icon-btn"
              aria-label={unread ? `Notificaciones: ${unread} sin leer` : 'Notificaciones'}
            >
              <IconBell size={21} />
              {unread > 0 && <span className="icon-count">{unread}</span>}
            </Link>
            <Link to="/carrito" className="cart-pill">
              <IconCart size={19} />
              Carrito · {count}
            </Link>
            <span className="user-chip" title={user.email}>
              <span className="avatar" aria-hidden="true">
                {initials(user.name)}
              </span>
              {user.name.split(' ')[0]}
              {isAdmin && <span className="role-tag">admin</span>}
            </span>
            <button className="btn btn-ghost btn-sm" onClick={handleLogout}>
              Salir
            </button>
          </>
        ) : (
          <>
            <NavLink to="/login" className="nav-link">
              Iniciar sesión
            </NavLink>
            <NavLink to="/registro" className="btn btn-rose btn-sm">
              Crear cuenta
            </NavLink>
          </>
        )}
      </nav>
    </header>
  )
}
