import { useEffect, useState } from 'react'
import { messagesApi } from '../api'
import { errorMessage } from '../api/client'
import { formatDate } from '../utils/files'

const EMPTY = { subject: '', content: '' }

export default function Mensajes() {
  const [form, setForm] = useState(EMPTY)
  const [list, setList] = useState(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)

  const load = () =>
    messagesApi
      .mine()
      .then(setList)
      .catch((err) => setError(errorMessage(err)))

  useEffect(() => {
    load()
  }, [])

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    setNotice('')
    try {
      await messagesApi.send({ subject: form.subject.trim(), content: form.content.trim() })
      setForm(EMPTY)
      setNotice('Tu mensaje se envió. Te responderemos lo antes posible.')
      load()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="page">
      <div className="title">
        <h1>Escríbenos</h1>
        <p className="muted">Envía tus dudas sobre productos o pedidos.</p>
      </div>
      <div className="split">
        <form className="side card form" onSubmit={onSubmit} style={{ flexBasis: 360 }}>
          <div className="field">
            <label htmlFor="msg-subject">Asunto</label>
            <input id="msg-subject" name="subject" value={form.subject} onChange={onChange} placeholder="Por ejemplo: estado de mi pedido" required />
          </div>
          <div className="field">
            <label htmlFor="msg-content">Mensaje</label>
            <textarea id="msg-content" name="content" rows={5} value={form.content} onChange={onChange} required />
          </div>
          {error && (
            <div className="alert alert-error" role="alert">
              {error}
            </div>
          )}
          {notice && (
            <div className="alert alert-ok" role="status">
              {notice}
            </div>
          )}
          <button className="btn btn-dark" disabled={saving}>
            {saving ? 'Enviando…' : 'Enviar mensaje'}
          </button>
        </form>
        <section className="main list" aria-label="Mis mensajes">
          <h2 style={{ fontSize: 22 }}>Mis mensajes</h2>
          {!list && !error && <p className="muted">Cargando…</p>}
          {list?.length === 0 && <p className="muted">Todavía no has enviado mensajes.</p>}
          {list?.map((m) => (
            <article key={m.id} className="list-item">
              <div className="grow">
                <strong>{m.subject}</strong>
                <span>{m.content}</span>
                {m.createdAt && <span className="muted" style={{ fontSize: 14 }}>{formatDate(m.createdAt)}</span>}
              </div>
            </article>
          ))}
        </section>
      </div>
    </div>
  )
}
