import {
  CallHandler,
  ExecutionContext,
  HttpException,
  Injectable,
  NestInterceptor,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request, Response } from 'express';
import { Observable, tap } from 'rxjs';
import type { AuthUser } from '../common/current-user.decorator.js';
import { AuditService } from './audit.service.js';

const AUDITED_METHODS = ['POST', 'PATCH', 'PUT', 'DELETE'];

/** Registra en audit.activity_log toda petición que modifica datos. No guarda el cuerpo (puede traer contraseñas). */
@Injectable()
export class AuditInterceptor implements NestInterceptor {
  private readonly instance: string;

  constructor(
    private readonly auditService: AuditService,
    config: ConfigService,
  ) {
    this.instance = config.get<string>('INSTANCE_NAME', 'backend');
  }

  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const http = context.switchToHttp();
    const request = http.getRequest<Request & { user?: AuthUser }>();
    if (!AUDITED_METHODS.includes(request.method)) return next.handle();

    const record = (statusCode: number) =>
      void this.auditService.record({
        userId: request.user?.id ?? null,
        method: request.method,
        path: request.originalUrl.split('?')[0].slice(0, 255),
        statusCode,
        instance: this.instance,
        ipAddress: request.ip ?? null,
      });

    return next.handle().pipe(
      tap({
        next: () => record(http.getResponse<Response>().statusCode),
        error: (error: unknown) =>
          record(error instanceof HttpException ? error.getStatus() : 500),
      }),
    );
  }
}
