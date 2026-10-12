import { Controller, Get, Param, ParseIntPipe, Query } from '@nestjs/common';
import { ApiQuery, ApiTags } from '@nestjs/swagger';
import { CategoriesService } from '../products/categories.service.js';
import { ProductsService } from '../products/products.service.js';

/** Lectura pública del catálogo (no requiere sesión). */
@ApiTags('catalog')
@Controller('catalog')
export class CatalogController {
  constructor(
    private readonly productsService: ProductsService,
    private readonly categoriesService: CategoriesService,
  ) {}

  @Get('categories')
  findCategories() {
    return this.categoriesService.findAll();
  }

  @Get('products')
  @ApiQuery({ name: 'search', required: false })
  @ApiQuery({ name: 'categoryId', required: false, type: Number })
  findAll(
    @Query('search') search?: string,
    @Query('categoryId', new ParseIntPipe({ optional: true }))
    categoryId?: number,
  ) {
    return this.productsService.findAll(
      search?.trim() || undefined,
      categoryId,
    );
  }

  @Get('products/:id')
  findOne(@Param('id', ParseIntPipe) id: number) {
    return this.productsService.findOne(id);
  }
}
