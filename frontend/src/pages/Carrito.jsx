import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ordersApi } from '../api'
import { errorMessage } from '../api/client'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { fileUrl, formatPrice, PLACEHOLDER_IMG } from '../utils/files'

export default function Carrito() {
  const { user } = useAuth()
  const cart = useCart()
  const navigate = useNavigate()
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const confirmar = async () => {
    if (!user) {
      navigate('/login', { state: { from: '/carrito' } })
      return
    }
    setSaving(true)
    setError('')
    try {
      const order = await ordersApi.create(
        cart.items.map((i) => ({ productId: i.productId, quantity: i.quantity })),
      )
      cart.clear()
      navigate(`/pago/${order.id}`, { state: { order } })
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSaving(false)
    }
  }

  if (cart.items.length === 0) {
    return (
      <div className="page">
        <h1>Tu carrito</h1>
        <section className="empty">
          <h2>Tu carrito está vacío</h2>
          <p>Explora el catálogo y agrega lo que te guste.</p>
          <Link to="/" className="btn btn-dark">
            Ir al catálogo
          </Link>
        </section>
      </div>
    )
  }

  return (
    <div className="page">
      <div className="title">
        <h1>Tu carrito</h1>
        <p className="muted">
          {cart.count} {cart.count === 1 ? 'producto listo' : 'productos listos'} para comprar
        </p>
      </div>

      {error && (
        <div className="alert alert-error" role="alert">
          <strong>No pudimos crear tu pedido</strong>
          {error}
        </div>
      )}

      <div className="split">
        <section aria-label="Productos del carrito" className="main list">
          {cart.items.map((it) => (
            <div key={it.productId} className="list-item">
              <img className="thumb" style={{ width: 68, height: 68 }} src={fileUrl(it.imageUrl)} alt="" onError={(e) => (e.currentTarget.src = PLACEHOLDER_IMG)} />
              <div className="grow">
                <Link to={`/producto/${it.productId}`} style={{ color: 'inherit', fontWeight: 700, textDecoration: 'none' }}>
                  {it.name}
                </Link>
                <span className="muted" style={{ fontSize: 14 }}>
                  {formatPrice(it.price)} cada uno
                </span>
              </div>
              <div className="qty" role="group" aria-label={`Cantidad de ${it.name}`}>
                <button aria-label="Quitar una unidad" disabled={it.quantity <= 1} onClick={() => cart.setQuantity(it.productId, it.quantity - 1)}>
                  −
                </button>
                <span>{it.quantity}</span>
                <button
                  aria-label="Agregar una unidad"
                  disabled={it.stock != null && it.quantity >= it.stock}
                  onClick={() => cart.setQuantity(it.productId, it.quantity + 1)}
                >
                  +
                </button>
              </div>
              <strong style={{ minWidth: 84, textAlign: 'right', fontSize: 19 }}>{formatPrice(it.price * it.quantity)}</strong>
              <button className="btn btn-ghost btn-sm" style={{ color: 'var(--err)' }} onClick={() => cart.remove(it.productId)}>
                Quitar
              </button>
            </div>
          ))}
        </section>

        <aside className="side card" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <h2 style={{ fontSize: 22 }}>Resumen</h2>
          <div className="summary-row muted">
            <span>{cart.count} unidades</span>
            <span>{formatPrice(cart.total)}</span>
          </div>
          <div className="summary-total">
            <span style={{ fontWeight: 700 }}>Total</span>
            <strong>{formatPrice(cart.total)}</strong>
          </div>
          <button className="btn btn-dark btn-block" onClick={confirmar} disabled={saving}>
            {saving && <span className="spinner" aria-hidden="true" />}
            {saving ? 'Creando pedido…' : 'Confirmar pedido'}
          </button>
          <p className="muted" style={{ fontSize: 14 }}>
            Al confirmar se reserva el stock. El pago se hace en el siguiente paso.
          </p>
        </aside>
      </div>
    </div>
  )
}
