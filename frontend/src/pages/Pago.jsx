import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { ordersApi, paymentsApi } from '../api'
import { errorMessage } from '../api/client'
import { IconCheck } from '../components/Icons'
import { formatPrice } from '../utils/files'
import { itemName, itemPrice, METHODS, orderItems, orderTotal } from '../utils/orders'

export default function Pago() {
  const { orderId } = useParams()
  const location = useLocation()
  const [order, setOrder] = useState(location.state?.order ?? null)
  const [paid, setPaid] = useState(null)
  const [method, setMethod] = useState('card')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(!location.state?.order)
  const [saving, setSaving] = useState(false)

  // Si se entra directo (p. ej. desde "Mis pedidos"), se busca el pedido y si ya tiene pago.
  useEffect(() => {
    let active = true
    Promise.all([
      order ? Promise.resolve(null) : ordersApi.mine(),
      paymentsApi.mine().catch(() => []),
    ])
      .then(([orders, payments]) => {
        if (!active) return
        if (orders) {
          const found = orders.find((o) => String(o.id) === String(orderId))
          if (found) setOrder(found)
          else setError('No encontramos ese pedido en tu cuenta.')
        }
        const payment = payments.find((p) => String(p.orderId) === String(orderId))
        if (payment) setPaid(payment)
        else if (orders?.find((o) => String(o.id) === String(orderId))?.status === 'paid') setPaid({})
      })
      .catch((err) => active && setError(errorMessage(err)))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [orderId])

  const pagar = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const payment = await paymentsApi.create(Number(orderId), method)
      setPaid({ method, ...payment })
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSaving(false)
    }
  }

  const total = orderTotal(order)

  if (paid) {
    const m = paid.method ? METHODS[paid.method] : null
    return (
      <div className="page">
        <section className="card" role="status" style={{ maxWidth: 560, width: '100%', margin: '12px auto 0', textAlign: 'center', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12, padding: '36px 24px' }}>
          <span className="tag-ok" style={{ width: 64, height: 64, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <IconCheck size={32} />
          </span>
          <h1>Pago registrado</h1>
          <p>
            Pedido n.º {orderId}
            {order ? ` · ${formatPrice(total)}` : ''}
            {m ? ` · ${m.label}` : ''}
          </p>
          <p className="muted">Te avisaremos por una notificación cuando haya novedades de tu pedido.</p>
          <div className="row" style={{ justifyContent: 'center' }}>
            <Link to="/pedidos" className="btn btn-dark">
              Ver mis pedidos
            </Link>
            <Link to="/" className="btn btn-outline">
              Seguir comprando
            </Link>
          </div>
        </section>
      </div>
    )
  }

  return (
    <div className="page">
      <div className="title">
        <h1>Pagar pedido n.º {orderId}</h1>
        <p className="muted">Tu pedido ya está creado. Solo falta el pago.</p>
      </div>

      {error && (
        <div className="alert alert-error" role="alert">
          <strong>No se pudo registrar el pago</strong>
          {error}
        </div>
      )}

      {loading ? (
        <p className="muted">Cargando pedido…</p>
      ) : (
        order && (
          <div className="split">
            <form className="main card form" onSubmit={pagar}>
              <fieldset className="methods">
                <legend style={{ padding: 0, marginBottom: 10, fontWeight: 700, fontSize: 22 }}>¿Cómo quieres pagar?</legend>
                {Object.entries(METHODS).map(([value, m]) => (
                  <label key={value} className="method">
                    <input type="radio" name="method" value={value} checked={method === value} onChange={() => setMethod(value)} />
                    <span style={{ display: 'flex', flexDirection: 'column' }}>
                      <strong>{m.label}</strong>
                      <span className="muted" style={{ fontSize: 14 }}>
                        {m.hint}
                      </span>
                    </span>
                  </label>
                ))}
              </fieldset>
              <button className="btn btn-dark btn-block" disabled={saving}>
                {saving && <span className="spinner" aria-hidden="true" />}
                {saving ? 'Registrando pago…' : `Pagar ${formatPrice(total)}`}
              </button>
            </form>
            <aside className="side card" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <h2 style={{ fontSize: 22 }}>Tu pedido</h2>
              {orderItems(order).map((it, i) => (
                <div key={i} className="summary-row muted">
                  <span>
                    {it.quantity} × {itemName(it)}
                  </span>
                  <span style={{ whiteSpace: 'nowrap' }}>{formatPrice(itemPrice(it) * Number(it.quantity ?? 1))}</span>
                </div>
              ))}
              <div className="summary-total">
                <span style={{ fontWeight: 700 }}>Total a pagar</span>
                <strong>{formatPrice(total)}</strong>
              </div>
            </aside>
          </div>
        )
      )}
    </div>
  )
}
