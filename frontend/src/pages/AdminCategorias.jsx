import { useEffect, useState } from 'react'
import { catalogApi, categoriesApi } from '../api'
import { errorMessage } from '../api/client'
import AdminTabs from '../components/AdminTabs'

const EMPTY = { name: '', description: '' }

export default function AdminCategorias() {
  const [list, setList] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)

  const load = () =>
    catalogApi
      .categories()
      .then(setList)
      .catch((err) => {
        setList((l) => l ?? [])
        setError(errorMessage(err))
      })

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
      const body = { name: form.name.trim() }
      if (form.description.trim()) body.description = form.description.trim()
      const saved = await categoriesApi.create(body)
      setNotice(`Se creó la categoría «${saved.name}».`)
      setForm(EMPTY)
      load()
    } catch (err) {
      setError(err.response?.status === 409 ? 'Ya existe una categoría con ese nombre.' : errorMessage(err))
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async (c) => {
    if (!window.confirm(`¿Eliminar la categoría «${c.name}»? Sus productos quedarán sin categoría.`)) return
    setError('')
    setNotice('')
    try {
      await categoriesApi.remove(c.id)
      setNotice(`Se eliminó la categoría «${c.name}».`)
      load()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <div className="page">
      <div className="title">
        <h1>Administración</h1>
        <p className="muted">{list ? `${list.length} categorías` : 'Cargando categorías…'}</p>
      </div>
      <AdminTabs />
      <div aria-live="polite">
        {notice && (
          <div className="alert alert-ok" role="status">
            {notice}
          </div>
        )}
      </div>
      <div className="split" style={{ flexDirection: 'row-reverse' }}>
        <form className="side card form" onSubmit={onSubmit} style={{ flexBasis: 320 }}>
          <h2 style={{ fontSize: 22 }}>Nueva categoría</h2>
          {error && (
            <div className="alert alert-error" role="alert">
              {error}
            </div>
          )}
          <div className="field">
            <label htmlFor="cat-name">Nombre</label>
            <input id="cat-name" name="name" value={form.name} onChange={onChange} placeholder="Por ejemplo: Juguetes" required />
          </div>
          <div className="field">
            <label htmlFor="cat-desc">
              Descripción <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span>
            </label>
            <textarea id="cat-desc" name="description" rows={3} value={form.description} onChange={onChange} />
          </div>
          <button className="btn btn-dark" disabled={saving}>
            {saving ? 'Guardando…' : 'Crear categoría'}
          </button>
        </form>
        <section className="main table-wrap" aria-label="Categorías">
          {list?.length === 0 && (
            <div className="empty" style={{ border: 0 }}>
              <h2>Todavía no hay categorías</h2>
            </div>
          )}
          {list?.length > 0 && (
            <table className="table">
              <thead>
                <tr>
                  <th scope="col">Categoría</th>
                  <th scope="col">Descripción</th>
                  <th scope="col">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {list.map((c) => (
                  <tr key={c.id}>
                    <td style={{ fontWeight: 700 }}>{c.name}</td>
                    <td className="muted">{c.description || '—'}</td>
                    <td>
                      <button className="btn btn-danger btn-sm" onClick={() => onDelete(c)}>
                        Eliminar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </div>
    </div>
  )
}
