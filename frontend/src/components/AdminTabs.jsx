import { NavLink } from 'react-router-dom'

const TABS = [
  ['/admin/productos', 'Productos'],
  ['/admin/pedidos', 'Pedidos'],
  ['/admin/mensajes', 'Mensajes'],
  ['/admin/usuarios', 'Usuarios'],
  ['/admin/categorias', 'Categorías'],
  ['/admin/auditoria', 'Auditoría'],
]

export default function AdminTabs() {
  return (
    <nav aria-label="Secciones de administración" className="tabs">
      {TABS.map(([to, label]) => (
        <NavLink key={to} to={to} className={({ isActive }) => `chip ${isActive ? 'chip-active' : ''}`} style={{ display: 'inline-flex', alignItems: 'center', textDecoration: 'none' }}>
          {label}
        </NavLink>
      ))}
    </nav>
  )
}
