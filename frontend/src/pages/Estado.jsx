import { useCallback, useEffect, useState } from 'react'
import { healthApi } from '../api'
import { API_URL, errorMessage } from '../api/client'
import { IconRefresh } from '../components/Icons'

const INSTANCES = ['backend-1', 'backend-2']

// backend-1 en carbón, backend-2 en rosa: se distinguen también por el texto.
const instClass = (name) => (String(name).endsWith('2') ? 'inst-2' : 'inst-1')

const label = (v) => (v === 'up' ? 'Disponible' : v === 'down' ? 'No disponible' : 'Sin datos')
const tagFor = (v) => (v === 'up' ? 'tag-ok' : v === 'down' ? 'tag-err' : '')

export default function Estado() {
  const [last, setLast] = useState(null)
  const [history, setHistory] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const consultar = useCallback(async () => {
    setLoading(true)
    setError('')
    const start = performance.now()
    try {
      const res = await healthApi.check()
      const entry = {
        ...res,
        instance: res.instance ?? res.headerInstance ?? 'desconocida',
        ms: Math.round(performance.now() - start),
        at: new Date(),
      }
      setLast(entry)
      setHistory((h) => [entry, ...h].slice(0, 12))
    } catch (err) {
      setLast(null)
      setError(errorMessage(err))
    } finally {
      setLoading(false)
    }
  }, [])

  // Primera consulta automática al entrar.
  useEffect(() => {
    consultar()
  }, [consultar])

  const total = history.length
  const counts = INSTANCES.map((name) => {
    const n = history.filter((h) => h.instance === name).length
    return { name, n, pct: total ? Math.round((n / total) * 100) : 0 }
  })
  const degraded = last && (last.status !== 'ok' || last.database !== 'up' || last.files !== 'up')

  return (
    <div className="page">
      <div className="page-head">
        <div style={{ flex: '1 1 420px' }}>
          <span className="mono muted" style={{ fontSize: 13 }}>
            GET {API_URL}/health · a través de NGINX
          </span>
          <h1 style={{ margin: '6px 0 8px' }}>Estado del sistema</h1>
          <p className="muted" style={{ maxWidth: 620 }}>
            Cada consulta entra por NGINX y la atiende una de las dos instancias del backend. Consulta
            varias veces para ver cómo se van turnando.
          </p>
        </div>
        <button className="btn btn-dark" onClick={consultar} disabled={loading} style={{ minHeight: 52 }}>
          {loading ? <span className="spinner" aria-hidden="true" /> : <IconRefresh />}
          {loading ? 'Consultando…' : 'Volver a consultar'}
        </button>
      </div>

      {error && (
        <section className="alert alert-error" role="alert">
          <strong>No se pudo conectar con el servidor</strong>
          {error}
        </section>
      )}

      {!last && !error && (
        <section className="card" aria-label="Consultando">
          <p className="muted">Consultando el estado…</p>
        </section>
      )}

      {last && (
        <section className={`status-result ${instClass(last.instance)}`} aria-live="polite">
          <div className="who" style={{ background: 'var(--inst-bg)' }}>
            <span style={{ fontWeight: 700 }}>Respondió esta consulta</span>
            <span className="instance-name" style={{ color: 'var(--inst)' }}>
              {last.instance}
            </span>
            <div className="row">
              <span className={`tag ${degraded ? 'tag-warn' : 'tag-ok'}`}>
                <span className="dot" />
                {degraded ? 'Degradado' : 'Operativo'}
              </span>
              {last.headerInstance && (
                <span className="mono" style={{ fontSize: 13 }}>
                  X-Backend-Instance: {last.headerInstance}
                </span>
              )}
            </div>
          </div>
          <dl className="facts">
            <div>
              <dt>Puerto</dt>
              <dd>{last.port ?? '—'}</dd>
            </div>
            <div>
              <dt>Tiempo de respuesta</dt>
              <dd>{last.ms} ms</dd>
            </div>
            <div>
              <dt>Equipo (host)</dt>
              <dd>{last.host ?? '—'}</dd>
            </div>
            <div>
              <dt>Hora de la consulta</dt>
              <dd>{last.at.toLocaleTimeString()}</dd>
            </div>
          </dl>
        </section>
      )}

      {last?.files === 'down' && (
        <div className="alert alert-warn" role="status">
          <strong>El servidor de archivos (L5) no responde.</strong>
          Las imágenes de los productos pueden no verse; el resto de la tienda sigue funcionando.
        </div>
      )}
      {last?.database === 'down' && (
        <div className="alert alert-error" role="status">
          <strong>La base de datos (L4) no responde.</strong>
          El catálogo, los pedidos y las cuentas no van a funcionar hasta que vuelva.
        </div>
      )}

      <section aria-label="Nodos" className="nodes">
        {counts.map((c) => {
          const active = last?.instance === c.name
          return (
            <div key={c.name} className={`node ${instClass(c.name)} ${active ? 'active' : ''}`}>
              <div className="row" style={{ justifyContent: 'space-between' }}>
                <span className="node-tag">L3 · BACKEND</span>
                {active && <span className="inst-chip" style={{ minWidth: 0, height: 26 }}>Respondió ahora</span>}
              </div>
              <span className="mono" style={{ fontSize: 20, fontWeight: 500, color: 'var(--inst)' }}>
                {c.name}
              </span>
              <div className="bar" aria-hidden="true">
                <div style={{ width: `${c.pct}%` }} />
              </div>
              <span className="muted" style={{ fontSize: 14 }}>
                {total ? `${c.n} de ${total} consultas` : 'Sin consultas todavía'}
              </span>
            </div>
          )
        })}
        {[
          ['L4 · BASE DE DATOS', 'PostgreSQL', last?.database],
          ['L5 · ARCHIVOS', 'Servidor de archivos', last?.files],
        ].map(([tag, name, value]) => (
          <div key={tag} className="node">
            <span className="node-tag">{tag}</span>
            <span style={{ fontSize: 20, fontWeight: 700 }}>{name}</span>
            <span>
              <span className={`tag ${tagFor(value)}`}>
                <span className="dot" />
                {label(value)}
              </span>
            </span>
          </div>
        ))}
      </section>

      {total > 0 && (
        <section className="card" aria-label="Historial" style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div className="row" style={{ justifyContent: 'space-between', alignItems: 'baseline' }}>
            <h2 style={{ fontSize: 22 }}>Últimas consultas</h2>
            <span className="muted" style={{ fontSize: 14 }}>
              {counts.map((c) => `${c.name}: ${c.n}`).join(' · ')}
            </span>
          </div>
          <div>
            <p className="muted" style={{ fontSize: 13, marginBottom: 8 }}>
              Orden en que respondieron (de la más antigua a la más reciente)
            </p>
            <div className="row" style={{ gap: 6 }}>
              {[...history].reverse().map((h, i) => (
                <span key={i} className={`inst-chip ${instClass(h.instance)}`}>
                  {h.instance}
                </span>
              ))}
            </div>
          </div>
          <div className="table-wrap" style={{ borderRadius: 14, border: '1px solid var(--ink-soft)' }}>
            <table className="table">
              <thead>
                <tr>
                  <th scope="col">Hora</th>
                  <th scope="col">Instancia</th>
                  <th scope="col">Puerto</th>
                  <th scope="col">Base de datos</th>
                  <th scope="col">Archivos</th>
                  <th scope="col" className="num">
                    Tiempo
                  </th>
                </tr>
              </thead>
              <tbody>
                {history.map((h, i) => (
                  <tr key={i}>
                    <td className="mono">{h.at.toLocaleTimeString()}</td>
                    <td>
                      <span className={`inst-chip ${instClass(h.instance)}`} style={{ height: 28 }}>
                        {h.instance}
                      </span>
                    </td>
                    <td className="mono">{h.port ?? '—'}</td>
                    <td>
                      <span className={`tag ${tagFor(h.database)}`}>{label(h.database)}</span>
                    </td>
                    <td>
                      <span className={`tag ${tagFor(h.files)}`}>{label(h.files)}</span>
                    </td>
                    <td className="num mono">{h.ms} ms</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  )
}
