import { useEffect, useState } from 'react'
import { notificationsApi } from '../api'
import { errorMessage } from '../api/client'
import { formatDate } from '../utils/files'
import { isRead, notificationText } from '../utils/notifications'

export default function Notificaciones() {
  const [list, setList] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    notificationsApi
      .list()
      .then((items) => setList([...items].sort((a, b) => new Date(b.createdAt ?? 0) - new Date(a.createdAt ?? 0))))
      .catch((err) => setError(errorMessage(err)))
  }, [])

  const markRead = async (n) => {
    setError('')
    try {
      await notificationsApi.markRead(n.id)
      setList((l) => l.map((x) => (x.id === n.id ? { ...x, read: true } : x)))
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const unread = list?.filter((n) => !isRead(n)).length ?? 0

  return (
    <div className="page">
      <div className="title">
        <h1>Notificaciones</h1>
        {list && <p className="muted">{unread ? `${unread} sin leer` : 'Estás al día'}</p>}
      </div>
      {error && (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      )}
      {!list && !error && <p className="muted">Cargando notificaciones…</p>}
      {list?.length === 0 && (
        <section className="empty">
          <h2>No tienes notificaciones</h2>
          <p>Aquí verás los avisos sobre tus pedidos y pagos.</p>
        </section>
      )}
      {list?.length > 0 && (
        <section className="list" aria-label="Notificaciones">
          {list.map((n) => (
            <article key={n.id} className={`list-item ${isRead(n) ? '' : 'unread'}`}>
              <div className="grow">
                <span style={{ fontWeight: isRead(n) ? 400 : 700 }}>{notificationText(n)}</span>
                {n.createdAt && <span className="muted" style={{ fontSize: 14 }}>{formatDate(n.createdAt)}</span>}
              </div>
              {isRead(n) ? (
                <span className="muted" style={{ fontSize: 14 }}>Leída</span>
              ) : (
                <button className="btn btn-outline btn-sm" onClick={() => markRead(n)}>
                  Marcar como leída
                </button>
              )}
            </article>
          ))}
        </section>
      )}
    </div>
  )
}
