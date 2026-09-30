import { createParamDecorator, ExecutionContext } from '@nestjs/common';
import type { Role } from '../users/user.entity.js';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: Role;
}

export const CurrentUser = createParamDecorator(
  (_data: unknown, context: ExecutionContext): AuthUser =>
    context.switchToHttp().getRequest().user,
);
