import { randomUUID } from 'node:crypto';
import { createParamDecorator, type ExecutionContext } from '@nestjs/common';
import type { NextFunction, Request, Response } from 'express';

export const CORRELATION_HEADER = 'X-Correlation-ID';

// Letras, números, puntos y guiones (máx. 100): evita que un valor arbitrario
// termine en los logs o en las cabeceras que se reenvían a L5.
const VALID_ID = /^[A-Za-z0-9._-]{1,100}$/;

type RequestWithCorrelation = Request & { correlationId?: string };

/**
 * Identifica cada operación de punta a punta (L2 → L1 → L3 → L5).
 * Si el cliente envía un X-Correlation-ID válido se conserva; si no, se genera uno.
 * Vuelve siempre en la cabecera de la respuesta, también cuando hay error.
 */
export function correlationIdMiddleware(
  req: RequestWithCorrelation,
  res: Response,
  next: NextFunction,
) {
  const received = req.header(CORRELATION_HEADER);
  const id = received && VALID_ID.test(received) ? received : randomUUID();
  req.correlationId = id;
  res.setHeader(CORRELATION_HEADER, id);
  next();
}

/** Inyecta en el controlador el X-Correlation-ID de la petición actual. */
export const CorrelationId = createParamDecorator(
  (_data: unknown, context: ExecutionContext) =>
    context.switchToHttp().getRequest<RequestWithCorrelation>().correlationId ??
    randomUUID(),
);
