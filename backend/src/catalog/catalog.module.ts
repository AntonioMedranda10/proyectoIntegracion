import { Module } from '@nestjs/common';
import { ProductsModule } from '../products/products.module.js';
import { CatalogController } from './catalog.controller.js';

@Module({
  imports: [ProductsModule],
  controllers: [CatalogController],
})
export class CatalogModule {}
