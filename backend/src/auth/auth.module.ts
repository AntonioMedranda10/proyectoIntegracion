import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtModule } from '@nestjs/jwt';
import { UsersModule } from '../users/users.module.js';
import { AuthController } from './auth.controller.js';
import { AuthService } from './auth.service.js';

@Module({
  imports: [
    UsersModule,
    JwtModule.registerAsync({
      // Global para que los guards de cualquier módulo puedan usar JwtService.
      global: true,
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        // Ambas instancias deben usar el MISMO secreto para aceptar los tokens de la otra.
        secret: config.get<string>('JWT_SECRET', 'cambia-este-secreto'),
        signOptions: {
          expiresIn: Number(config.get('JWT_EXPIRES_SECONDS', 86400)),
        },
      }),
    }),
  ],
  controllers: [AuthController],
  providers: [AuthService],
})
export class AuthModule {}
