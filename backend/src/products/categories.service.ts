import {
  BadRequestException,
  ConflictException,
  Injectable,
  NotFoundException,
  OnApplicationBootstrap,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Category } from './category.entity.js';
import type { CreateCategoryDto } from './dto/create-category.dto.js';

const DEFAULT_CATEGORIES = [
  { name: 'Electrónica', description: 'Audio, computación y accesorios' },
  { name: 'Hogar', description: 'Artículos para la casa' },
  { name: 'Ropa', description: 'Prendas y calzado' },
  { name: 'Deportes', description: 'Equipamiento y accesorios deportivos' },
];

@Injectable()
export class CategoriesService implements OnApplicationBootstrap {
  constructor(
    @InjectRepository(Category)
    private readonly categories: Repository<Category>,
    private readonly config: ConfigService,
  ) {}

  /** Crea las categorías iniciales (solo en la instancia que sincroniza la base). */
  async onApplicationBootstrap() {
    if (this.config.get('DB_SYNC') !== 'true') return;
    if ((await this.categories.count()) > 0) return;
    await this.categories.save(DEFAULT_CATEGORIES);
  }

  findAll() {
    return this.categories.find({ order: { name: 'ASC' } });
  }

  async create(dto: CreateCategoryDto) {
    if (await this.categories.existsBy({ name: dto.name })) {
      throw new ConflictException(`La categoría "${dto.name}" ya existe`);
    }
    return this.categories.save(this.categories.create(dto));
  }

  async remove(id: number) {
    const category = await this.categories.findOneBy({ id });
    if (!category) throw new NotFoundException(`Categoría ${id} no encontrada`);
    await this.categories.remove(category);
    return { deleted: true };
  }

  /** Valida que la categoría exista antes de asignarla a un producto. */
  async ensureExists(id: number) {
    if (!(await this.categories.existsBy({ id }))) {
      throw new BadRequestException(`La categoría ${id} no existe`);
    }
  }
}
