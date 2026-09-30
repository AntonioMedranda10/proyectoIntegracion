import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { Logger, ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { NestFactory } from '@nestjs/core';
import type { NestExpressApplication } from '@nestjs/platform-express';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';
import type { NextFunction, Request, Response } from 'express';
import { AppModule } from './app.module.js';

async function bootstrap() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule);
  const config = app.get(ConfigService);
  const port = Number(config.get('PORT', 3001));
  const instance = config.get<string>('INSTANCE_NAME', `backend-${port}`);

  app.setGlobalPrefix('api');
  app.enableCors();
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      forbidNonWhitelisted: true,
      transform: true,
    }),
  );

  // Permite ver en cualquier respuesta qué instancia la atendió (útil para demostrar el balanceo).
  app.use((_req: Request, res: Response, next: NextFunction) => {
    res.setHeader('X-Backend-Instance', instance);
    next();
  });

  // En modo local (sin L5) el propio backend sirve las imágenes en /files.
  if (config.get('FILES_MODE', 'local') === 'local') {
    const uploadsDir = resolve(config.get<string>('UPLOADS_DIR', 'uploads'));
    mkdirSync(uploadsDir, { recursive: true });
    app.useStaticAssets(uploadsDir, { prefix: '/files/' });
  }

  const swaggerConfig = new DocumentBuilder()
    .setTitle('E-commerce API (L3)')
    .setDescription('API REST del backend - arquitectura de 5 nodos')
    .setVersion('1.0')
    .addBearerAuth()
    .build();
  SwaggerModule.setup(
    'api/docs',
    app,
    SwaggerModule.createDocument(app, swaggerConfig),
  );

  await app.listen(port, '0.0.0.0');
  Logger.log(
    `${instance} escuchando en http://0.0.0.0:${port}/api`,
    'Bootstrap',
  );
}
await bootstrap();
