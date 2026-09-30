import { applyDecorators, SetMetadata, UseGuards } from '@nestjs/common';
import {
  ApiBearerAuth,
  ApiForbiddenResponse,
  ApiUnauthorizedResponse,
} from '@nestjs/swagger';
import type { Role } from '../users/user.entity.js';
import { JwtAuthGuard, RolesGuard, ROLES_KEY } from './auth.guards.js';

/** Protege una ruta con JWT. Si se indican roles, además exige alguno de ellos. */
export function Auth(...roles: Role[]) {
  return applyDecorators(
    SetMetadata(ROLES_KEY, roles),
    UseGuards(JwtAuthGuard, RolesGuard),
    ApiBearerAuth(),
    ApiUnauthorizedResponse({ description: 'Token ausente o inválido' }),
    ApiForbiddenResponse({ description: 'Rol sin permiso' }),
  );
}
