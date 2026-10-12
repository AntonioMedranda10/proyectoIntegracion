import { Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { TypeOrmModule } from '@nestjs/typeorm';
import { DataSource } from 'typeorm';
import { AuditModule } from './audit/audit.module.js';
import { AuthModule } from './auth/auth.module.js';
import { CatalogModule } from './catalog/catalog.module.js';
import { DB_SCHEMAS, DB_TABLE_COMMENTS } from './common/db-schemas.js';
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
        // La sincronización se hace en dataSourceFactory, después de crear los schemas.
        synchronize: false,
        retryAttempts: 5,
        retryDelay: 3000,
      }),
      dataSourceFactory: async (options) => {
        const dataSource = await new DataSource(options!).initialize();
        // Solo una instancia debe crear/actualizar la estructura para evitar choques al arrancar las dos.
        if (process.env.DB_SYNC === 'true') {
          // TypeORM crea tablas pero no schemas: se crean antes de sincronizar.
          for (const schema of Object.keys(DB_SCHEMAS)) {
            await dataSource.query(`CREATE SCHEMA IF NOT EXISTS ${schema}`);
          }
          await dataSource.synchronize();
          // Descripciones visibles en pgAdmin (propiedades de cada schema y tabla).
          const literal = (text: string) => `'${text.replaceAll("'", "''")}'`;
          for (const [schema, comment] of Object.entries(DB_SCHEMAS)) {
            await dataSource.query(
              `COMMENT ON SCHEMA ${schema} IS ${literal(comment)}`,
            );
          }
          for (const [table, comment] of Object.entries(DB_TABLE_COMMENTS)) {
            await dataSource.query(
              `COMMENT ON TABLE ${table} IS ${literal(comment)}`,
            );
          }
        }
        return dataSource;
      },
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
    AuditModule,
  ],
})
export class AppModule {}
