import { useEffect, useState } from 'react'
import { Navigate, useParams } from 'react-router-dom'
import { auditApi, messagesApi, ordersApi, usersApi } from '../api'
import { errorMessage } from '../api/client'
import AdminTabs from '../components/AdminTabs'
import { formatDate, formatPrice } from '../utils/files'
import { orderSummary, orderTotal, STATUS } from '../utils/orders'

// Los pedidos y mensajes traen solo userId: se cruza con GET /users para mostrar el nombre.
const who = (row, users) => {
  const u = row.user ?? users[row.userId]
  return u ? u.name : row.userId ? `Usuario n.º ${row.userId}` : '—'
}

const dateTime = (v) => (v ? new Date(v).toLocaleString('es') : '')

// Listas de solo lectura del administrador.
const SECTIONS = {
  pedidos: {
    title: 'Pedidos',
    load: ordersApi.all,
    columns: [
      ['N.º', (o) => o.id],
      ['Cliente', who],
      ['Productos', (o) => orderSummary(o).names || '—'],
      ['Estado', (o) => <span className={`tag ${STATUS[o.status]?.tag ?? ''}`}>{STATUS[o.status]?.label ?? o.status}</span>],
      ['Total', (o) => formatPrice(orderTotal(o)), 'num'],
      ['Fecha', (o) => formatDate(o.createdAt)],
    ],
  },
  mensajes: {
    title: 'Mensajes',
    load: messagesApi.all,
    columns: [
      ['De', who],
      ['Asunto', (m) => m.subject],
      ['Mensaje', (m) => m.content],
      ['Fecha', (m) => formatDate(m.createdAt)],
    ],
  },
  usuarios: {
    title: 'Usuarios',
    load: usersApi.all,
    columns: [
      ['N.º', (u) => u.id],
      ['Nombre', (u) => u.name],
      ['Email', (u) => u.email],
      ['Rol', (u) => <span className={`tag ${u.role === 'admin' ? 'tag-rose' : ''}`}>{u.role}</span>],
      ['Registro', (u) => formatDate(u.createdAt)],
    ],
  },
  auditoria: {
    title: 'Operaciones',
    hint: 'Últimas 100 operaciones que modificaron datos, con la instancia del backend que las atendió.',
    load: auditApi.list,
    columns: [
      ['Fecha', (a) => dateTime(a.createdAt)],
      ['Usuario', (a, users) => (a.userId ? who(a, users) : 'Sin sesión')],
      ['Operación', (a) => <span className="mono">{`${a.method} ${a.path}`}</span>],
      ['Código', (a) => <span className={`tag ${a.statusCode < 400 ? 'tag-ok' : 'tag-err'}`}>{a.statusCode}</span>],
      ['Instancia', (a) => <span className={`inst-chip ${String(a.instance).endsWith('2') ? 'inst-2' : 'inst-1'}`} style={{ height: 28 }}>{a.instance}</span>],
    ],
  },
}

export default function AdminRegistros() {
  const { section } = useParams()
  const config = SECTIONS[section]
  const [rows, setRows] = useState(null)
  const [users, setUsers] = useState({})
  const [error, setError] = useState('')

  useEffect(() => {
    if (!config) return
    let active = true
    setRows(null)
    setError('')
    Promise.all([config.load(), usersApi.all().catch(() => [])])
      .then(([list, userList]) => {
        if (!active) return
        setRows(list)
        setUsers(Object.fromEntries(userList.map((u) => [u.id, u])))
      })
      .catch((err) => active && setError(errorMessage(err)))
    return () => {
      active = false
    }
  }, [config])

  if (!config) return <Navigate to="/admin/productos" replace />

  return (
    <div className="page">
      <div className="title">
        <h1>Administración</h1>
        <p className="muted">
          {rows ? `${rows.length} ${config.title.toLowerCase()}` : `Cargando ${config.title.toLowerCase()}…`}
          {config.hint ? ` · ${config.hint}` : ''}
        </p>
      </div>
      <AdminTabs />
      {error && (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      )}
      {rows?.length === 0 && (
        <section className="empty">
          <h2>No hay {config.title.toLowerCase()} todavía</h2>
        </section>
      )}
      {rows?.length > 0 && (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr>
                {config.columns.map(([label, , cls]) => (
                  <th key={label} scope="col" className={cls}>
                    {label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row, i) => (
                <tr key={row.id ?? i}>
                  {config.columns.map(([label, get, cls]) => (
                    <td key={label} className={cls}>
                      {get(row, users)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
