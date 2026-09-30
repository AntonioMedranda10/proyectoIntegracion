import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { ILike, Repository } from 'typeorm';
import { FilesService } from '../files/files.service.js';
import type { CreateProductDto } from './dto/create-product.dto.js';
import type { UpdateProductDto } from './dto/update-product.dto.js';
import { Product } from './product.entity.js';

@Injectable()
export class ProductsService {
  constructor(
    @InjectRepository(Product) private readonly products: Repository<Product>,
    private readonly filesService: FilesService,
  ) {}

  findAll(search?: string) {
    return this.products.find({
      where: search ? { name: ILike(`%${search}%`) } : {},
      order: { id: 'DESC' },
    });
  }

  async findOne(id: number) {
    const product = await this.products.findOneBy({ id });
    if (!product) throw new NotFoundException(`Producto ${id} no encontrado`);
    return product;
  }

  create(dto: CreateProductDto) {
    return this.products.save(this.products.create(dto));
  }

  async update(id: number, dto: UpdateProductDto) {
    const product = await this.findOne(id);
    return this.products.save(this.products.merge(product, dto));
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
}
