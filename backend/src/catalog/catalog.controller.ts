import { Controller, Get, Param, ParseIntPipe, Query } from '@nestjs/common';
import { ApiQuery, ApiTags } from '@nestjs/swagger';
import { ProductsService } from '../products/products.service.js';

/** Lectura pública del catálogo (no requiere sesión). */
@ApiTags('catalog')
@Controller('catalog')
export class CatalogController {
  constructor(private readonly productsService: ProductsService) {}

  @Get('products')
  @ApiQuery({ name: 'search', required: false })
  findAll(@Query('search') search?: string) {
    return this.productsService.findAll(search?.trim() || undefined);
  }

  @Get('products/:id')
  findOne(@Param('id', ParseIntPipe) id: number) {
    return this.productsService.findOne(id);
  }
}
