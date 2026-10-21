import { useEffect, useRef, useState } from 'react'
import { catalogApi, productsApi } from '../api'
import { errorMessage } from '../api/client'
import AdminTabs from '../components/AdminTabs'
import { fileUrl, formatPrice, PLACEHOLDER_IMG } from '../utils/files'

const EMPTY = { name: '', description: '', price: '', stock: '', categoryId: '' }
const MAX_SIZE = 5 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'image/webp']

const mb = (bytes) => `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`

export default function AdminProductos() {
  const [products, setProducts] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [editing, setEditing] = useState(null)
  const [file, setFile] = useState(null)
  const [preview, setPreview] = useState(null)
  const [fileError, setFileError] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [saving, setSaving] = useState(false)
  const fileInput = useRef(null)
  const formRef = useRef(null)

  const load = () =>
    catalogApi
      .list()
      .then(setProducts)
      .catch((err) => {
        setProducts((p) => p ?? [])
        setError(errorMessage(err))
      })

  const [categories, setCategories] = useState([])

  useEffect(() => {
    load()
    catalogApi
      .categories()
      .then(setCategories)
      .catch(() => setCategories([]))
  }, [])

  // Vista previa local de la imagen elegida (no se sube hasta guardar).
  useEffect(() => {
    if (!file) return setPreview(null)
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const clearFileInput = () => {
    if (fileInput.current) fileInput.current.value = ''
  }

  const onFile = (e) => {
    const f = e.target.files[0] ?? null
    setFileError('')
    if (f && !TYPES.includes(f.type)) {
      setFileError(`${f.name} no es JPG, PNG ni WEBP.`)
      clearFileInput()
      return setFile(null)
    }
    if (f && f.size > MAX_SIZE) {
      setFileError(`${f.name} pesa ${mb(f.size)} y el máximo es 5 MB. Elige una imagen más liviana.`)
      clearFileInput()
      return setFile(null)
    }
    setFile(f)
  }

  const reset = () => {
    setForm(EMPTY)
    setEditing(null)
    setFile(null)
    setFileError('')
    clearFileInput()
  }

  const onEdit = (p) => {
    reset()
    setEditing(p)
    setForm({
      name: p.name,
      description: p.description ?? '',
      price: String(p.price),
      stock: String(p.stock),
      categoryId: p.categoryId ? String(p.categoryId) : '',
    })
    setNotice('')
    setError('')
    formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const onSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    setNotice('')
    const body = {
      name: form.name.trim(),
      price: Number(form.price),
      stock: Number(form.stock),
    }
    const description = form.description.trim()
    if (description || editing) body.description = description
    if (form.categoryId) body.categoryId = Number(form.categoryId)
    try {
      const saved = editing
        ? await productsApi.update(editing.id, body)
        : await productsApi.create(body)
      // La imagen se sube después, cuando el producto ya tiene id.
      // El frontend se la manda al backend (L3) y es el backend quien la guarda en L5.
      // Si la subida falla, el formulario queda editando ese producto para no duplicarlo al reintentar.
      setEditing(saved)
      if (file) await productsApi.uploadImage(saved.id, file)
      setNotice(
        `Se ${editing ? 'actualizó' : 'creó'} «${saved.name}»` +
          (file ? ' y su imagen ya se ve en el catálogo.' : '.'),
      )
      reset()
      load()
    } catch (err) {
      setError(errorMessage(err))
      load()
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async (p) => {
    if (!window.confirm(`¿Eliminar «${p.name}»? Esta acción no se puede deshacer.`)) return
    setError('')
    setNotice('')
    try {
      await productsApi.remove(p.id)
      setNotice(`Se eliminó «${p.name}».`)
      if (editing?.id === p.id) reset()
      load()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const currentImage = preview ?? (editing?.imageUrl ? fileUrl(editing.imageUrl) : null)

  return (
    <div className="page">
      <div className="title">
        <h1>Administración</h1>
        <p className="muted">
          {products ? `${products.length} productos en la tienda` : 'Cargando productos…'}
        </p>
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
        <form ref={formRef} className="side card form" onSubmit={onSubmit} style={{ flexBasis: 340 }}>
          <h2 style={{ fontSize: 22 }}>{editing ? `Editar producto n.º ${editing.id}` : 'Nuevo producto'}</h2>

          {error && (
            <div className="alert alert-error" role="alert">
              <strong>No se pudo guardar</strong>
              {error}
            </div>
          )}

          <div className="field">
            <label htmlFor="adm-name">Nombre</label>
            <input id="adm-name" name="name" value={form.name} onChange={onChange} placeholder="Por ejemplo: Webcam Full HD 1080p" required />
          </div>
          <div className="field-row">
            <div className="field">
              <label htmlFor="adm-price">Precio ($)</label>
              <input id="adm-price" name="price" type="number" step="0.01" min="0" value={form.price} onChange={onChange} placeholder="0.00" required />
            </div>
            <div className="field">
              <label htmlFor="adm-stock">Stock</label>
              <input id="adm-stock" name="stock" type="number" step="1" min="0" value={form.stock} onChange={onChange} placeholder="0" required />
            </div>
          </div>
          {categories.length > 0 && (
            <div className="field">
              <label htmlFor="adm-category">
                Categoría <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span>
              </label>
              <select id="adm-category" name="categoryId" value={form.categoryId} onChange={onChange}>
                <option value="">Sin categoría</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div className="field">
            <label htmlFor="adm-desc">
              Descripción <span className="muted" style={{ fontWeight: 400 }}>(opcional)</span>
            </label>
            <textarea id="adm-desc" name="description" rows={3} value={form.description} onChange={onChange} placeholder="Qué es y qué incluye" />
          </div>

          <div className="field">
            <span className="field-label">Imagen</span>
            <div className={`upload ${fileError ? 'invalid' : ''}`}>
              {currentImage && <img className="upload-preview" src={currentImage} alt="" onError={(e) => (e.currentTarget.src = PLACEHOLDER_IMG)} />}
              <div className="upload-text">
                <strong style={{ color: fileError ? 'var(--err)' : undefined }}>
                  {fileError ? 'No se puede subir esa imagen' : file ? file.name : editing?.imageUrl ? 'Imagen actual' : 'Aún no elegiste una imagen'}
                </strong>
                <span className="field-hint" style={{ color: fileError ? 'var(--err)' : undefined }}>
                  {fileError || (file ? `${mb(file.size)} · se subirá al guardar` : 'JPG, PNG o WEBP · máximo 5 MB')}
                </span>
              </div>
              <label htmlFor="adm-file" className="btn btn-outline btn-sm" style={{ background: 'var(--surface)' }}>
                {file || editing?.imageUrl ? 'Cambiar' : 'Elegir imagen'}
              </label>
              <input ref={fileInput} id="adm-file" type="file" accept="image/jpeg,image/png,image/webp" onChange={onFile} className="sr-only" />
            </div>
          </div>

          <div className="row">
            <button className="btn btn-dark" style={{ flex: '1 1 160px' }} disabled={saving}>
              {saving && <span className="spinner" aria-hidden="true" />}
              {saving ? 'Guardando…' : editing ? 'Guardar cambios' : 'Crear producto'}
            </button>
            {editing && (
              <button type="button" className="btn btn-outline" onClick={reset}>
                Cancelar
              </button>
            )}
          </div>
        </form>

        <section aria-label="Lista de productos" className="main table-wrap">
          {!products && (
            <div style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 12 }} aria-label="Cargando productos">
              {[0, 1, 2, 3, 4].map((n) => (
                <div key={n} className="skeleton" style={{ height: 48 }} />
              ))}
            </div>
          )}
          {products?.length === 0 && (
            <div className="empty" style={{ border: 0 }}>
              <h2>Todavía no hay productos</h2>
              <p>Crea el primero con el formulario «Nuevo producto» y aparecerá aquí y en el catálogo.</p>
            </div>
          )}
          {products?.length > 0 && (
            <table className="table">
              <thead>
                <tr>
                  <th scope="col">Producto</th>
                  <th scope="col" className="num">
                    Precio
                  </th>
                  <th scope="col" className="num">
                    Stock
                  </th>
                  <th scope="col">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {products.map((p) => (
                  <tr key={p.id} className={editing?.id === p.id ? 'selected' : ''}>
                    <td>
                      <div className="cell-product">
                        <img className="thumb" src={fileUrl(p.imageUrl)} alt="" onError={(e) => (e.currentTarget.src = PLACEHOLDER_IMG)} />
                        <div style={{ minWidth: 0 }}>
                          <div style={{ fontWeight: 700 }}>{p.name}</div>
                          <div className="mono muted" style={{ fontSize: 13 }}>
                            n.º {p.id} · {p.category?.name ?? 'sin categoría'} · {p.imageUrl ? 'con imagen' : 'sin imagen'}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="num" style={{ fontWeight: 700 }}>
                      {formatPrice(p.price)}
                    </td>
                    <td className="num" style={{ fontWeight: 700, color: p.stock <= 0 ? 'var(--err)' : p.stock <= 3 ? 'var(--rose-text)' : undefined }}>
                      {p.stock <= 0 ? 'Agotado' : p.stock}
                    </td>
                    <td>
                      <div className="row" style={{ gap: 8, flexWrap: 'nowrap' }}>
                        <button className="btn btn-outline btn-sm" onClick={() => onEdit(p)}>
                          Editar
                        </button>
                        <button className="btn btn-danger btn-sm" onClick={() => onDelete(p)}>
                          Eliminar
                        </button>
                      </div>
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
