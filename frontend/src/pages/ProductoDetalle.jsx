import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { catalogApi } from '../api'
import { errorMessage } from '../api/client'
import { IconBack, IconCart } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { fileUrl, formatPrice, PLACEHOLDER_IMG } from '../utils/files'

export default function ProductoDetalle() {
  const { id } = useParams()
  const { user } = useAuth()
  const cart = useCart()
  const navigate = useNavigate()
  const [product, setProduct] = useState(null)
  const [error, setError] = useState('')
  const [notFound, setNotFound] = useState(false)
  const [qty, setQty] = useState(1)
  const [notice, setNotice] = useState(null)

  useEffect(() => {
    let active = true
    setProduct(null)
    setError('')
    setNotFound(false)
    catalogApi
      .get(id)
      .then((p) => active && setProduct(p))
      .catch((err) => {
        if (!active) return
        if (err.response?.status === 404) setNotFound(true)
        else setError(errorMessage(err))
      })
    return () => {
      active = false
    }
  }, [id])

  const inCart = product ? (cart.items.find((i) => i.productId === product.id)?.quantity ?? 0) : 0
  const available = product ? product.stock - inCart : 0
  const soldOut = product ? product.stock <= 0 : false

  const addToCart = () => {
    if (!user) {
      navigate('/login', { state: { from: `/producto/${id}`, reason: 'cart' } })
      return
    }
    if (qty > available) {
      setNotice({
        type: 'error',
        text:
          available > 0
            ? `Stock insuficiente: pediste ${qty} y solo puedes agregar ${available} más.`
            : 'Ya tienes en el carrito todas las unidades disponibles.',
      })
      return
    }
    cart.add(product, qty)
    setNotice({ type: 'ok', text: `Agregaste ${qty} × ${product.name} al carrito.` })
    setQty(1)
  }

  return (
    <div className="page">
      <Link to="/" className="back-link">
        <IconBack size={18} /> Volver al catálogo
      </Link>

      {notFound && (
        <section className="empty">
          <span className="mono muted">Error 404</span>
          <h2>Este producto ya no está disponible</h2>
          <p>Puede que lo hayan retirado de la tienda o que el enlace esté mal escrito.</p>
          <Link to="/" className="btn btn-dark">
            Ver el catálogo
          </Link>
        </section>
      )}

      {error && (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      )}

      {!product && !error && !notFound && (
        <section className="detail" aria-label="Cargando producto">
          <div className="detail-media skeleton" style={{ borderRadius: 0 }} />
          <div className="detail-info" aria-hidden="true">
            <div className="skeleton" style={{ height: 34, width: '80%' }} />
            <div className="skeleton" style={{ height: 30, width: '35%' }} />
            <div className="skeleton" style={{ height: 14, width: '100%' }} />
            <div className="skeleton" style={{ height: 14, width: '70%' }} />
          </div>
        </section>
      )}

      {product && (
        <section className="detail">
          <div className="detail-media">
            <img
              src={fileUrl(product.imageUrl)}
              alt={product.name}
              onError={(e) => (e.currentTarget.src = PLACEHOLDER_IMG)}
            />
          </div>
          <div className="detail-info">
            <span className="mono muted" style={{ fontSize: 13 }}>
              Producto n.º {product.id}
              {product.category ? ` · ${product.category.name}` : ''}
            </span>
            <h1>{product.name}</h1>
            <div className="row">
              <span className="price-lg">{formatPrice(product.price)}</span>
              <span className={`tag ${soldOut ? 'tag-err' : product.stock <= 3 ? 'tag-soft' : 'tag-ok'}`}>
                <span className="dot" />
                {soldOut
                  ? 'Agotado'
                  : product.stock <= 3
                    ? `Quedan ${product.stock} unidades`
                    : `${product.stock} unidades disponibles`}
              </span>
            </div>
            {product.description && <p className="muted">{product.description}</p>}

            <div aria-live="polite">
              {notice && (
                <div className={`alert ${notice.type === 'ok' ? 'alert-ok' : 'alert-error'}`}>
                  {notice.text}{' '}
                  {notice.type === 'ok' && (
                    <Link to="/carrito" style={{ color: 'inherit', fontWeight: 700 }}>
                      Ver carrito
                    </Link>
                  )}
                </div>
              )}
            </div>

            <div className="row" style={{ alignItems: 'flex-end' }}>
              <div className="field">
                <span className="field-label" id="qty-label">
                  Cantidad
                </span>
                <div className="qty" role="group" aria-labelledby="qty-label">
                  <button
                    aria-label="Quitar una unidad"
                    disabled={soldOut || qty <= 1}
                    onClick={() => setQty((q) => q - 1)}
                  >
                    −
                  </button>
                  <span aria-live="polite">{qty}</span>
                  <button
                    aria-label="Agregar una unidad"
                    disabled={soldOut}
                    onClick={() => setQty((q) => q + 1)}
                  >
                    +
                  </button>
                </div>
              </div>
              <button className="btn btn-dark" style={{ flex: '1 1 200px' }} disabled={soldOut} onClick={addToCart}>
                <IconCart size={19} />
                {soldOut ? 'Agotado' : 'Agregar al carrito'}
              </button>
            </div>
            {soldOut && <p className="muted">Por ahora no se puede comprar. Vuelve a revisar más adelante.</p>}
          </div>
        </section>
      )}
    </div>
  )
}
