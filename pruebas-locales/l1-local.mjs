// Puerta de entrada LOCAL que imita a NGINX (L1), para probar en un solo PC.
// En la red del aula se usa el NGINX real; esto solo reproduce sus reglas:
//   /api/*   → L3 en round-robin (backend-1 :3001 y backend-2 :3002)
//   /files/* → L5 (servidor de archivos)
//
//   node pruebas-locales/l1-local.mjs
//
// Variables: L1_PUERTO (8080), L1_BACKENDS (127.0.0.1:3001,127.0.0.1:3002), L1_ARCHIVOS (127.0.0.1:8090).
import { createServer, request } from 'node:http';

const PUERTO = Number(process.env.L1_PUERTO ?? 8080);
const BACKENDS = (process.env.L1_BACKENDS ?? '127.0.0.1:3001,127.0.0.1:3002').split(',');
const ARCHIVOS = process.env.L1_ARCHIVOS ?? '127.0.0.1:8090';
let turno = 0;

const hora = () => new Date().toTimeString().slice(0, 8);

createServer((req, res) => {
  const destino = req.url.startsWith('/api/')
    ? BACKENDS[turno++ % BACKENDS.length]
    : req.url.startsWith('/files/')
      ? ARCHIVOS
      : null;
  if (!destino) {
    res.writeHead(404, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ statusCode: 404, message: 'L1 local: solo enruta /api y /files' }));
  }
  const [host, port] = destino.split(':');
  const salida = request(
    {
      host,
      port,
      method: req.method,
      path: req.url,
      headers: { ...req.headers, 'x-forwarded-for': req.socket.remoteAddress },
    },
    (respuesta) => {
      console.log(
        `[${hora()}] L1 ${req.method} ${req.url} → ${destino} ${respuesta.statusCode}` +
          ` · correlation=${respuesta.headers['x-correlation-id'] ?? req.headers['x-correlation-id'] ?? '-'}`,
      );
      res.writeHead(respuesta.statusCode, respuesta.headers);
      respuesta.pipe(res);
    },
  );
  // Como NGINX: si el destino no responde, 502 Bad Gateway.
  salida.on('error', (error) => {
    console.log(`[${hora()}] L1 ${req.method} ${req.url} → ${destino} 502 · ${error.code}`);
    if (res.headersSent) return res.destroy();
    res.writeHead(502, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ statusCode: 502, message: `L1 local: no se pudo conectar con ${destino}`, error: 'Bad Gateway' }));
  });
  req.pipe(salida);
}).listen(PUERTO, '127.0.0.1', () =>
  console.log(`L1 local en http://127.0.0.1:${PUERTO} · /api → ${BACKENDS.join(' y ')} · /files → ${ARCHIVOS}`),
);
