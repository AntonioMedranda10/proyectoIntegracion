import { Injectable, UnauthorizedException } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { compare } from 'bcryptjs';
import type { User } from '../users/user.entity.js';
import { UsersService } from '../users/users.service.js';
import type { LoginDto } from './dto/login.dto.js';
import type { RegisterDto } from './dto/register.dto.js';

@Injectable()
export class AuthService {
  constructor(
    private readonly usersService: UsersService,
    private readonly jwt: JwtService,
  ) {}

  async register(dto: RegisterDto) {
    const user = await this.usersService.create(
      dto.name,
      dto.email,
      dto.password,
    );
    return this.buildSession(user);
  }

  async login(dto: LoginDto) {
    const user = await this.usersService.findByEmailWithPassword(dto.email);
    if (!user || !(await compare(dto.password, user.passwordHash))) {
      throw new UnauthorizedException('Credenciales inválidas');
    }
    return this.buildSession(user);
  }

  private async buildSession(user: User) {
    const publicUser = {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
    };
    const access_token = await this.jwt.signAsync({
      sub: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
    });
    return { access_token, user: publicUser };
  }
}
