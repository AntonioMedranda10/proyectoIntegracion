import { createContext, useContext, useEffect, useState } from 'react'
import { useAuth } from './AuthContext'

// El carrito vive solo en el navegador (localStorage), uno por usuario.
// El backend recién se entera al confirmar: POST /api/orders.
const CartContext = createContext(null)

const keyFor = (user) => `cart:${user?.id ?? 'visitante'}`

function load(key) {
  try {
    const items = JSON.parse(localStorage.getItem(key))
    return Array.isArray(items) ? items : []
  } catch {
    return []
  }
}

export function CartProvider({ children }) {
  const { user } = useAuth()
  const key = keyFor(user)
  const [cart, setCart] = useState(() => ({ key, items: load(key) }))

  // Si cambia el usuario (login/logout), se carga su propio carrito.
  const items = cart.key === key ? cart.items : load(key)

  useEffect(() => {
    try {
      localStorage.setItem(cart.key, JSON.stringify(cart.items))
    } catch {
      // sin almacenamiento disponible: el carrito vive solo en memoria
    }
  }, [cart])

  const update = (fn) => setCart({ key, items: fn(items) })

  const add = (product, quantity = 1) =>
    update((list) => {
      const found = list.find((i) => i.productId === product.id)
      if (found) {
        return list.map((i) =>
          i.productId === product.id ? { ...i, quantity: i.quantity + quantity } : i,
        )
      }
      return [
        ...list,
        {
          productId: product.id,
          name: product.name,
          price: Number(product.price),
          imageUrl: product.imageUrl,
          stock: product.stock,
          quantity,
        },
      ]
    })

  const setQuantity = (productId, quantity) =>
    update((list) =>
      list.map((i) => (i.productId === productId ? { ...i, quantity: Math.max(1, quantity) } : i)),
    )

  const remove = (productId) => update((list) => list.filter((i) => i.productId !== productId))

  const value = {
    items,
    count: items.reduce((sum, i) => sum + i.quantity, 0),
    total: items.reduce((sum, i) => sum + i.price * i.quantity, 0),
    add,
    setQuantity,
    remove,
    clear: () => update(() => []),
  }

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export const useCart = () => useContext(CartContext)
