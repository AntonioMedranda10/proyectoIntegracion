import { IconCheck } from './Icons'

// Marco común de login y registro: formulario a la izquierda, panel oscuro a la derecha.
export default function AuthLayout({ children }) {
  return (
    <div className="page" style={{ maxWidth: 960 }}>
      <div className="split" style={{ background: 'var(--surface)', borderRadius: 26, overflow: 'hidden', gap: 0 }}>
        <div className="main" style={{ flexBasis: 380, padding: 'clamp(24px, 5vw, 40px)' }}>
          {children}
        </div>
        <aside
          className="side card-dark"
          style={{ padding: 'clamp(24px, 5vw, 40px)', display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 20, alignSelf: 'stretch' }}
        >
          <h2 style={{ fontSize: 24 }}>Con tu cuenta puedes</h2>
          <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: 16 }}>
            {[
              'Comprar productos del catálogo y pagar tus pedidos',
              'Revisar el historial de tus pedidos y pagos',
              'Recibir notificaciones y escribirle a la tienda',
            ].map((text) => (
              <li key={text} style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                <span style={{ color: 'var(--rose-soft)', marginTop: 2 }}>
                  <IconCheck size={20} />
                </span>
                {text}
              </li>
            ))}
          </ul>
        </aside>
      </div>
    </div>
  )
}
