import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { AuthModule } from './auth/auth.module.js';
import { CatalogModule } from './catalog/catalog.module.js';
import { FilesModule } from './files/files.module.js';
import { HealthModule } from './health/health.module.js';
import { MessagesModule } from './messages/messages.module.js';
import { NotificationsModule } from './notifications/notifications.module.js';
import { OrdersModule } from './orders/orders.module.js';
import { PaymentsModule } from './payments/payments.module.js';
import { ProductsModule } from './products/products.module.js';
import { UsersModule } from './users/users.module.js';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    TypeOrmModule.forRootAsync({
      inject: [ConfigService],
      useFactory: (config: ConfigService) => ({
        type: 'postgres',
        host: config.get('DB_HOST', 'localhost'),
        port: Number(config.get('DB_PORT', 5432)),
        username: config.get('DB_USER', 'postgres'),
        password: config.get('DB_PASSWORD', ''),
        database: config.get('DB_NAME', 'ecommerce_dev'),
        autoLoadEntities: true,
        // Solo una instancia debe crear/actualizar las tablas para evitar choques al arrancar las dos.
        synchronize: config.get('DB_SYNC') === 'true',
        retryAttempts: 5,
        retryDelay: 3000,
      }),
    }),
    AuthModule,
    UsersModule,
    ProductsModule,
    CatalogModule,
    OrdersModule,
    PaymentsModule,
    FilesModule,
    MessagesModule,
    NotificationsModule,
    HealthModule,
  ],
})
export class AppModule {}
