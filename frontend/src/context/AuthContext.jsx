import { createContext, useContext, useEffect, useState } from 'react'
import { authApi } from '../api'
import { session } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(session.getUser)

  // Al recargar la página, confirma que el token siga siendo válido.
  useEffect(() => {
    if (!session.getToken()) return
    authApi
      .me()
      .then((me) => {
        session.save(session.getToken(), me)
        setUser(me)
      })
      .catch(() => {}) // el interceptor ya limpia la sesión si fue 401
  }, [])

  // El interceptor de axios avisa cuando el token venció.
  useEffect(() => {
    const onExpired = () => setUser(null)
    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [])

  const startSession = ({ access_token, user }) => {
    session.save(access_token, user)
    setUser(user)
    return user
  }

  const value = {
    user,
    isAdmin: user?.role === 'admin',
    login: (credentials) => authApi.login(credentials).then(startSession),
    register: (body) => authApi.register(body).then(startSession),
    logout: () => {
      session.clear()
      setUser(null)
    },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => useContext(AuthContext)
