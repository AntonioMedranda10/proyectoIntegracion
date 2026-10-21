import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { catalogApi } from '../api'
import { errorMessage } from '../api/client'
import { IconHeadphones, IconKeyboard, IconLamp, IconPlus } from '../components/Icons'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { fileUrl, formatPrice, PLACEHOLDER_IMG } from '../utils/files'

const FILTERS = [
  { id: 'todos', label: 'Todos', test: () => true },
  { id: 'disponibles', label: 'Disponibles', test: (p) => p.stock > 0 },
  { id: 'ultimas', label: 'Últimas unidades', test: (p) => p.stock > 0 && p.stock <= 3 },
  { id: 'agotados', label: 'Agotados', test: (p) => p.stock <= 0 },
]

const SORTS = {
  'precio-asc': { label: 'Precio: de menor a mayor', fn: (a, b) => a.price - b.price },
  'precio-desc': { label: 'Precio: de mayor a menor', fn: (a, b) => b.price - a.price },
  nombre: { label: 'Nombre: A a Z', fn: (a, b) => a.name.localeCompare(b.name, 'es') },
}

const onImgError = (e) => (e.currentTarget.src = PLACEHOLDER_IMG)

function Hero({ user }) {
  return (
    <>
      <section className="hero">
        <div className="hero-text">
          <span className="tag tag-rose">{user ? `Hola, ${user.name.split(' ')[0]}` : 'Bienvenida'}</span>
          <h1>Tecnología para tu escritorio, sin complicaciones</h1>
          <p>Compra desde tu cuenta, paga como prefieras y sigue cada pedido desde la tienda.</p>
          <div className="row">
            <a href="#productos" className="btn btn-light">
              Ver productos
            </a>
            {user ? (
              <Link to="/pedidos" className="btn btn-outline-light">
                Mis pedidos
              </Link>
            ) : (
              <Link to="/registro" className="btn btn-outline-light">
                Crear cuenta
              </Link>
            )}
          </div>
        </div>
        <div className="hero-art" aria-hidden="true">
          <div className="big">
            <IconHeadphones size={90} />
          </div>
          <div className="mid">
            <IconKeyboard size={48} />
          </div>
          <div className="light">
            <IconLamp size={48} />
          </div>
        </div>
      </section>

      <section aria-label="Cómo comprar" className="steps">
        <div className="step">
          <span className="step-num" style={{ background: 'var(--rose-soft)', color: 'var(--rose-text)' }}>
            1
          </span>
          <span>
            <strong>Elige tus productos</strong>
            <small>Busca en el catálogo y agrégalos al carrito.</small>
          </span>
        </div>
        <div className="step">
          <span className="step-num" style={{ background: 'var(--ink-soft)' }}>
            2
          </span>
          <span>
            <strong>Paga como prefieras</strong>
            <small>Tarjeta, efectivo o transferencia.</small>
          </span>
        </div>
        <div className="step">
          <span className="step-num" style={{ background: 'var(--ink)', color: 'var(--beige)' }}>
            3
          </span>
          <span>
            <strong>Sigue tu pedido</strong>
            <small>Revisa su estado y recibe avisos en tu cuenta.</small>
          </span>
        </div>
      </section>
    </>
  )
}

function Tile({ product, tone, onAdd }) {
  const soldOut = product.stock <= 0
  return (
    <div className={`tile tone-${tone}`}>
      <Link to={`/producto/${product.id}`} className="tile-link">
        <img className="tile-img" src={fileUrl(product.imageUrl)} alt="" onError={onImgError} />
        {product.category && (
          <span className="muted" style={{ fontSize: 13, fontWeight: 700 }}>
            {product.category.name}
          </span>
        )}
        <span className="tile-name">{product.name}</span>
      </Link>
      <div className="tile-foot">
        <span className="tile-price">
          <strong>{formatPrice(product.price)}</strong>
          {product.stock <= 3 && <small>{soldOut ? 'Agotado' : `Quedan ${product.stock}`}</small>}
        </span>
        <button
          className="btn btn-round"
          onClick={() => onAdd(product)}
          disabled={soldOut}
          aria-label={soldOut ? `${product.name} está agotado` : `Agregar ${product.name} al carrito`}
        >
          <IconPlus size={20} />
        </button>
      </div>
    </div>
  )
}

function Featured({ product, onAdd }) {
  const soldOut = product.stock <= 0
  return (
    <div className="tile-featured">
      <span className="tag tag-rose" style={{ alignSelf: 'flex-start' }}>
        Destacado
      </span>
      <img className="tile-img" src={fileUrl(product.imageUrl)} alt="" onError={onImgError} />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        <div className="row" style={{ justifyContent: 'space-between', alignItems: 'flex-end' }}>
          <Link to={`/producto/${product.id}`} className="name">
            {product.name}
          </Link>
          <span className="price">{formatPrice(product.price)}</span>
        </div>
        {product.description && <p>{product.description}</p>}
        <div className="row">
          <Link to={`/producto/${product.id}`} className="btn btn-light">
            Ver producto
          </Link>
          <button className="btn btn-rose" onClick={() => onAdd(product)} disabled={soldOut}>
            {soldOut ? 'Agotado' : 'Agregar al carrito'}
          </button>
        </div>
      </div>
    </div>
  )
}

function Skeletons() {
  return (
    <section aria-label="Cargando productos" className="mosaic">
      {[0, 1, 2, 3, 4, 5, 6, 7].map((n) => (
        <div key={n} className="tile tone-0" aria-hidden="true">
          <div className="skeleton" style={{ aspectRatio: '4 / 3' }} />
          <div className="skeleton" style={{ height: 14, width: '80%' }} />
          <div className="skeleton" style={{ height: 18, width: '40%' }} />
        </div>
      ))}
    </section>
  )
}

export default function Catalogo() {
  const { user } = useAuth()
  const cart = useCart()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const search = params.get('search')?.trim() ?? ''

  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [categoryId, setCategoryId] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [filter, setFilter] = useState('todos')
  const [sort, setSort] = useState('precio-asc')
  const [notice, setNotice] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    catalogApi
      .categories()
      .then(setCategories)
      .catch(() => setCategories([]))
  }, [])

  // El backend filtra por texto (search) y por categoría (categoryId).
  useEffect(() => {
    let active = true
    setLoading(true)
    setError('')
    catalogApi
      .list(search, categoryId)
      .then((list) => active && setProducts(Array.isArray(list) ? list : []))
      .catch((err) => active && setError(errorMessage(err)))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [search, categoryId, reload])

  const visible = useMemo(() => {
    const f = FILTERS.find((x) => x.id === filter)
    return products.filter(f.test).sort(SORTS[sort].fn)
  }, [products, filter, sort])

  // El destacado es el primer producto con stock (solo en la vista general).
  const showFeatured = !search && !categoryId && filter === 'todos' && visible.length > 2
  const featured = showFeatured ? (visible.find((p) => p.stock > 0) ?? visible[0]) : null
  const rest = featured ? visible.filter((p) => p.id !== featured.id) : visible

  const addToCart = (product) => {
    if (!user) {
      navigate('/login', { state: { from: '/', reason: 'cart' } })
      return
    }
    const inCart = cart.items.find((i) => i.productId === product.id)?.quantity ?? 0
    if (inCart >= product.stock) {
      setNotice(`Ya tienes en el carrito todas las unidades disponibles de ${product.name}.`)
      return
    }
    cart.add(product)
    setNotice(`Agregaste ${product.name} al carrito.`)
  }

  return (
    <div className="page">
      {!search && <Hero user={user} />}

      <div className="page-head" id="productos">
        <div className="title">
          <h2>{search ? `Resultados para «${search}»` : 'Productos'}</h2>
          {!loading && !error && (
            <span className="muted">
              {products.length} {products.length === 1 ? 'producto' : 'productos'}
            </span>
          )}
        </div>
        <div className="row" style={{ alignItems: 'flex-end' }}>
          {categories.length > 0 && (
            <div className="field">
              <label htmlFor="category" className="field-label" style={{ fontSize: 13, color: 'var(--muted)' }}>
                Categoría
              </label>
              <select id="category" className="select" value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
                <option value="">Todas</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div className="field">
            <label htmlFor="sort" className="field-label" style={{ fontSize: 13, color: 'var(--muted)' }}>
              Ordenar por
            </label>
            <select id="sort" className="select" value={sort} onChange={(e) => setSort(e.target.value)}>
              {Object.entries(SORTS).map(([id, s]) => (
                <option key={id} value={id}>
                  {s.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      <div role="group" aria-label="Filtrar por disponibilidad" className="filters">
        {FILTERS.map((f) => (
          <button
            key={f.id}
            className="chip"
            aria-pressed={filter === f.id}
            onClick={() => setFilter(f.id)}
          >
            {f.label} <span>{products.filter(f.test).length}</span>
          </button>
        ))}
      </div>

      <div aria-live="polite">{notice && <div className="alert alert-info">{notice}</div>}</div>

      {error && (
        <section className="alert alert-error" role="alert">
          <strong>No pudimos cargar el catálogo</strong>
          <span>{error}</span>
          <div style={{ marginTop: 10 }}>
            <button className="btn btn-dark btn-sm" onClick={() => setReload((n) => n + 1)}>
              Reintentar
            </button>
          </div>
        </section>
      )}

      {loading && <Skeletons />}

      {!loading && !error && visible.length === 0 && (
        <section className="empty">
          <h2>{search ? `No encontramos «${search}»` : 'No hay productos aquí'}</h2>
          <p>
            {search
              ? 'Prueba con otra palabra o revisa todos los productos disponibles.'
              : 'Prueba con otro filtro u otra categoría.'}
          </p>
          {categoryId && !search ? (
            <button className="btn btn-dark" onClick={() => setCategoryId('')}>
              Ver todas las categorías
            </button>
          ) : search ? (
            <Link to="/" className="btn btn-dark">
              Ver todo el catálogo
            </Link>
          ) : (
            <button className="btn btn-dark" onClick={() => setFilter('todos')}>
              Ver todos
            </button>
          )}
        </section>
      )}

      {!loading && !error && visible.length > 0 && (
        <section aria-label="Productos" className="mosaic">
          {featured && <Featured product={featured} onAdd={addToCart} />}
          {rest.map((p, i) => (
            <Tile key={p.id} product={p} tone={i % 3} onAdd={addToCart} />
          ))}
        </section>
      )}

      {user && cart.count > 0 && (
        <div className="cart-bar">
          <span>
            <strong>
              {cart.count} {cart.count === 1 ? 'producto' : 'productos'}
            </strong>{' '}
            en tu carrito · <strong>{formatPrice(cart.total)}</strong>
          </span>
          <Link to="/carrito" className="btn btn-rose">
            Ver carrito
          </Link>
        </div>
      )}
    </div>
  )
}
