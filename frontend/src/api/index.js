import { api } from './client'

const data = (res) => res.data

export const healthApi = {
  // Devuelve también el header X-Backend-Instance para comprobar el balanceo.
  check: () =>
    api.get('/health').then((res) => ({
      ...res.data,
      headerInstance: res.headers['x-backend-instance'] ?? null,
    })),
}

export const authApi = {
  register: (body) => api.post('/auth/register', body).then(data),
  login: (body) => api.post('/auth/login', body).then(data),
  me: () => api.get('/auth/me').then(data),
}

export const catalogApi = {
  list: (search, categoryId) => {
    const params = {}
    if (search) params.search = search
    if (categoryId) params.categoryId = categoryId
    return api.get('/catalog/products', { params }).then(data)
  },
  get: (id) => api.get(`/catalog/products/${id}`).then(data),
  categories: () => api.get('/catalog/categories').then(data),
}

export const categoriesApi = {
  create: (body) => api.post('/categories', body).then(data),
  remove: (id) => api.delete(`/categories/${id}`).then(data),
}

export const auditApi = {
  list: () => api.get('/audit').then(data),
}

export const productsApi = {
  create: (body) => api.post('/products', body).then(data),
  update: (id, body) => api.patch(`/products/${id}`, body).then(data),
  remove: (id) => api.delete(`/products/${id}`).then(data),
  uploadImage: (id, file) => {
    const form = new FormData()
    form.append('file', file)
    return api.post(`/products/${id}/image`, form).then(data)
  },
}

export const ordersApi = {
  create: (items) => api.post('/orders', { items }).then(data),
  mine: () => api.get('/orders/my').then(data),
  all: () => api.get('/orders').then(data),
}

export const paymentsApi = {
  create: (orderId, method) => api.post('/payments', { orderId, method }).then(data),
  mine: () => api.get('/payments/my').then(data),
}

export const notificationsApi = {
  list: () => api.get('/notifications').then(data),
  markRead: (id) => api.patch(`/notifications/${id}/read`).then(data),
}

export const messagesApi = {
  send: (body) => api.post('/messages', body).then(data),
  mine: () => api.get('/messages/my').then(data),
  all: () => api.get('/messages').then(data),
}

export const usersApi = {
  all: () => api.get('/users').then(data),
}
