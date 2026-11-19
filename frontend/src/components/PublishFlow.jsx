// Resultado de "Publicar producto con imagen": qué hizo cada nodo (L4, L5) y cómo terminó.
// status: completed (todo bien) · degraded (L5 falló, producto sin imagen) · failed (L4 falló, nada publicado)

const RESULTS = {
  ok: { label: 'OK', tag: 'tag-ok' },
  failed: { label: 'Falló', tag: 'tag-err' },
  skipped: { label: 'Omitido', tag: '' },
  compensated: { label: 'Compensado', tag: 'tag-warn' },
}

const TONES = {
  completed: { alert: 'alert-ok', title: 'Producto publicado' },
  degraded: { alert: 'alert-warn', title: 'Producto publicado sin imagen' },
  failed: { alert: 'alert-error', title: 'No se publicó el producto' },
}

export default function PublishFlow({ flow, onRetry, retrying, onClose }) {
  const tone = TONES[flow.status] ?? TONES.failed
  const name = flow.product?.name

  return (
    <div className={`alert ${tone.alert}`} role={flow.status === 'failed' ? 'alert' : 'status'} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div className="row" style={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <strong>
            {tone.title}
            {name ? ` · «${name}»` : ''}
          </strong>
          {flow.status === 'completed' && <span>{flow.image?.status === 'stored' ? 'La imagen quedó en el servidor de archivos (L5) y el producto en la base de datos (L4).' : 'Se guardó en la base de datos (L4) sin imagen.'}</span>}
          {flow.status === 'degraded' && (
            <span>
              El servidor de archivos (L5) no respondió ({flow.image?.reason}). El producto ya está en el catálogo con la imagen por defecto; la imagen quedó pendiente.
            </span>
          )}
          {flow.status === 'failed' && <span>{flow.message}</span>}
        </div>
        <button type="button" className="btn btn-ghost btn-sm" onClick={onClose} aria-label="Cerrar resultado">
          ✕
        </button>
      </div>

      <ol aria-label="Pasos de la orquestación" style={{ margin: 0, paddingLeft: 20, display: 'flex', flexDirection: 'column', gap: 4 }}>
        {flow.steps?.map((s, i) => {
          const r = RESULTS[s.result] ?? RESULTS.failed
          return (
            <li key={i}>
              <span className="mono" style={{ fontWeight: 700 }}>{s.node}</span> · {s.step}{' '}
              <span className={`tag ${r.tag}`}>{r.label}</span>{' '}
              <span className="mono" style={{ fontSize: 13 }}>
                {s.ms} ms{s.detail && s.result !== 'ok' ? ` · ${s.detail}` : ''}
              </span>
            </li>
          )
        })}
      </ol>

      <div className="row" style={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <span className="mono" style={{ fontSize: 13 }}>
          X-Correlation-ID: {flow.correlationId}
        </span>
        {flow.status === 'degraded' && onRetry && (
          <button type="button" className="btn btn-dark btn-sm" onClick={onRetry} disabled={retrying}>
            {retrying && <span className="spinner" aria-hidden="true" />}
            {retrying ? 'Subiendo imagen…' : 'Reintentar la imagen'}
          </button>
        )}
      </div>
    </div>
  )
}
