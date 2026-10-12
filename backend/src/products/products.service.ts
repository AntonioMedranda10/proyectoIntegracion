import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { type FindOptionsWhere, ILike, Repository } from 'typeorm';
import { FilesService } from '../files/files.service.js';
import { CategoriesService } from './categories.service.js';
import type { Category } from './category.entity.js';
import type { CreateProductDto } from './dto/create-product.dto.js';
import type { UpdateProductDto } from './dto/update-product.dto.js';
import { Product } from './product.entity.js';

@Injectable()
export class ProductsService {
  constructor(
    @InjectRepository(Product) private readonly products: Repository<Product>,
    private readonly filesService: FilesService,
    private readonly categoriesService: CategoriesService,
  ) {}

  findAll(search?: string, categoryId?: number) {
    const where: FindOptionsWhere<Product> = {};
    if (search) where.name = ILike(`%${search}%`);
    if (categoryId) where.categoryId = categoryId;
    return this.products.find({ where, order: { id: 'DESC' } });
  }

  async findOne(id: number) {
    const product = await this.products.findOneBy({ id });
    if (!product) throw new NotFoundException(`Producto ${id} no encontrado`);
    return product;
  }

  async create(dto: CreateProductDto) {
    const { categoryId, ...data } = dto;
    const product = this.products.create(data);
    await this.assignCategory(product, categoryId);
    return this.reload(await this.products.save(product));
  }

  async update(id: number, dto: UpdateProductDto) {
    const { categoryId, ...data } = dto;
    const product = this.products.merge(await this.findOne(id), data);
    await this.assignCategory(product, categoryId);
    return this.reload(await this.products.save(product));
  }

  async remove(id: number) {
    await this.products.remove(await this.findOne(id));
    return { deleted: true };
  }

  async setImage(
    id: number,
    file: Express.Multer.File | undefined,
    userId: number,
  ) {
    const product = await this.findOne(id);
    const record = await this.filesService.upload(
      file,
      { type: 'product', id },
      userId,
    );
    product.imageUrl = record.url;
    return this.products.save(product);
  }

  /** undefined = no cambiar la categoría. */
  private async assignCategory(product: Product, categoryId?: number) {
    if (categoryId === undefined) return;
    await this.categoriesService.ensureExists(categoryId);
    product.categoryId = categoryId;
    product.category = { id: categoryId } as Category;
  }

  /** Vuelve a leer el producto para devolverlo con los datos de su categoría. */
  private reload(product: Product) {
    return this.findOne(product.id);
  }
}
