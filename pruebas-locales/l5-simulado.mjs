// Servidor de archivos (L5) SIMULADO, con el contrato que espera L3 (backend/README.md, sección 6).
// Sirve para probar en un solo PC; en la red del aula se usa el L5 real.
//
//   node pruebas-locales/l5-simulado.mjs
//
// Variables: L5_PUERTO (8090), L5_API_KEY (clave que exige en x-api-key),
//            L5_DEMORA_MS (retraso al subir, para simular un L5 lento), L5_CARPETA.
import { createServer } from 'node:http';
import { randomUUID } from 'node:crypto';
import { mkdir, readFile, unlink, writeFile } from 'node:fs/promises';
import { basename, extname, join, resolve } from 'node:path';

const PUERTO = Number(process.env.L5_PUERTO ?? 8090);
const CLAVE = process.env.L5_API_KEY ?? '';
const DEMORA = Number(process.env.L5_DEMORA_MS ?? 0);
const CARPETA = resolve(process.env.L5_CARPETA ?? 'pruebas-locales/archivos-l5');
const TIPOS = { '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp' };

await mkdir(CARPETA, { recursive: true });

const hora = () => new Date().toTimeString().slice(0, 8);
const json = (res, codigo, cuerpo) => {
  res.writeHead(codigo, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(cuerpo));
};
const leerCuerpo = async (req) => {
  const partes = [];
  for await (const parte of req) partes.push(parte);
  return Buffer.concat(partes);
};
const claveValida = (req) => !CLAVE || req.headers['x-api-key'] === CLAVE;

createServer(async (req, res) => {
  const correlacion = req.headers['x-correlation-id'] ?? '-';
  const registrar = (codigo, extra = '') =>
    console.log(`[${hora()}] L5 ${req.method} ${req.url} → ${codigo} · correlation=${correlacion}${extra}`);
  try {
    if (req.method === 'GET' && req.url === '/') {
      return json(res, 200, { status: 'ok', servicio: 'L5 simulado' });
    }

    if (req.method === 'POST' && req.url === '/upload') {
      if (!claveValida(req)) return registrar(401), json(res, 401, { message: 'x-api-key inválida' });
      if (DEMORA) await new Promise((fin) => setTimeout(fin, DEMORA));
      const cuerpo = await leerCuerpo(req);
      // Request/Response de Node entienden multipart/form-data sin librerías extra.
      const form = await new Response(cuerpo, { headers: { 'content-type': req.headers['content-type'] ?? '' } }).formData();
      const archivo = form.get('file');
      if (!archivo || typeof archivo === 'string') return registrar(400), json(res, 400, { message: 'Falta el campo "file"' });
      const clave = randomUUID() + extname(archivo.name).toLowerCase();
      await writeFile(join(CARPETA, clave), Buffer.from(await archivo.arrayBuffer()));
      registrar(201, ` · clave=${clave}`);
      return json(res, 201, { storage_key: clave, url: `/files/${clave}` });
    }

    const enArchivos = req.url.startsWith('/files/') ? basename(decodeURIComponent(req.url.slice(7))) : null;
    if (enArchivos && req.method === 'GET') {
      const datos = await readFile(join(CARPETA, enArchivos)).catch(() => null);
      if (!datos) return registrar(404), json(res, 404, { message: 'No existe' });
      res.writeHead(200, { 'Content-Type': TIPOS[extname(enArchivos)] ?? 'application/octet-stream' });
      return res.end(datos);
    }
    if (enArchivos && req.method === 'DELETE') {
      if (!claveValida(req)) return registrar(401), json(res, 401, { message: 'x-api-key inválida' });
      const borrado = await unlink(join(CARPETA, enArchivos)).then(() => true, () => false);
      registrar(borrado ? 200 : 404, ` · clave=${enArchivos}`);
      return json(res, borrado ? 200 : 404, { deleted: borrado });
    }

    registrar(404);
    json(res, 404, { message: 'Ruta no encontrada' });
  } catch (error) {
    registrar(500, ` · ${error.message}`);
    json(res, 500, { message: 'Error interno de L5 simulado' });
  }
}).listen(PUERTO, '127.0.0.1', () =>
  console.log(`L5 simulado en http://127.0.0.1:${PUERTO} · carpeta ${CARPETA}${CLAVE ? ' · exige x-api-key' : ''}${DEMORA ? ` · demora ${DEMORA} ms` : ''}`),
);
