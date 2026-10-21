import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ordersApi, paymentsApi } from '../api'
import { errorMessage } from '../api/client'
import { formatDate, formatPrice } from '../utils/files'
import { METHODS, orderSummary, orderTotal, STATUS } from '../utils/orders'

export default function Pedidos() {
  const [orders, setOrders] = useState(null)
  const [payments, setPayments] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    Promise.all([ordersApi.mine(), paymentsApi.mine().catch(() => [])])
      .then(([o, p]) => {
        if (!active) return
        setOrders([...o].sort((a, b) => new Date(b.createdAt ?? 0) - new Date(a.createdAt ?? 0)))
        setPayments(p)
      })
      .catch((err) => active && setError(errorMessage(err)))
    return () => {
      active = false
    }
  }, [])

  const paymentFor = (order) => payments.find((p) => String(p.orderId ?? p.order?.id) === String(order.id))

  return (
    <div className="page">
      <div className="title">
        <h1>Mis pedidos</h1>
        {orders && <p className="muted">{orders.length === 1 ? '1 pedido' : `${orders.length} pedidos`}</p>}
      </div>

      {error && (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      )}
      {!orders && !error && <p className="muted">Cargando pedidos…</p>}

      {orders?.length === 0 && (
        <section className="empty">
          <h2>Todavía no tienes pedidos</h2>
          <p>Cuando confirmes una compra, aparecerá aquí con su estado de pago.</p>
          <Link to="/" className="btn btn-dark">
            Ir al catálogo
          </Link>
        </section>
      )}

      {orders?.length > 0 && (
        <section aria-label="Pedidos" className="list">
          {orders.map((o) => {
            const payment = paymentFor(o)
            const { names } = orderSummary(o)
            const status = STATUS[o.status] ?? { label: o.status, tag: '' }
            const pending = o.status === 'pending'
            return (
              <article key={o.id} className="list-item">
                <div className="grow">
                  <div className="row">
                    <strong style={{ fontSize: 20 }}>Pedido n.º {o.id}</strong>
                    <span className={`tag ${status.tag}`}>
                      <span className="dot" />
                      {status.label}
                      {o.status === 'paid' && payment ? ` con ${METHODS[payment.method]?.label.toLowerCase() ?? payment.method}` : ''}
                    </span>
                  </div>
                  {o.createdAt && <span className="muted" style={{ fontSize: 14 }}>{formatDate(o.createdAt)}</span>}
                  {names && <span>{names}</span>}
                </div>
                <strong style={{ fontSize: 24 }}>{formatPrice(orderTotal(o))}</strong>
                {pending && (
                  <Link to={`/pago/${o.id}`} state={{ order: o }} className="btn btn-dark">
                    Pagar ahora
                  </Link>
                )}
              </article>
            )
          })}
        </section>
      )}
    </div>
  )
}
