import {
  CanActivate,
  ExecutionContext,
  ForbiddenException,
  Injectable,
  UnauthorizedException,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { JwtService } from '@nestjs/jwt';
import type { Role } from '../users/user.entity.js';
import type { AuthUser } from './current-user.decorator.js';

export const ROLES_KEY = 'roles';

@Injectable()
export class JwtAuthGuard implements CanActivate {
  constructor(private readonly jwt: JwtService) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const request = context.switchToHttp().getRequest();
    const [type, token] = (request.headers.authorization ?? '').split(' ');
    if (type !== 'Bearer' || !token) {
      throw new UnauthorizedException('Falta el token de acceso');
    }
    try {
      const payload = await this.jwt.verifyAsync(token);
      const user: AuthUser = {
        id: payload.sub,
        name: payload.name,
        email: payload.email,
        role: payload.role,
      };
      request.user = user;
      return true;
    } catch {
      throw new UnauthorizedException('Token inválido o vencido');
    }
  }
}

@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private readonly reflector: Reflector) {}

  canActivate(context: ExecutionContext): boolean {
    const roles = this.reflector.getAllAndOverride<Role[]>(ROLES_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);
    if (!roles?.length) return true;
    const user: AuthUser = context.switchToHttp().getRequest().user;
    if (!roles.includes(user.role)) {
      throw new ForbiddenException('No tienes permiso para esta acción');
    }
    return true;
  }
}
