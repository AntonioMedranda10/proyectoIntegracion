import {
  HttpException,
  Injectable,
  Logger,
  ServiceUnavailableException,
} from '@nestjs/common';
import { DataSource } from 'typeorm';
import { FilesService, type StoredFile } from '../files/files.service.js';
import { CategoriesService } from './categories.service.js';
import type { Category } from './category.entity.js';
import type { PublishProductDto } from './dto/publish-product.dto.js';
import { Product } from './product.entity.js';
import { ProductsService } from './products.service.js';

export type PublishStatus = 'completed' | 'degraded';
export type ImageStatus = 'stored' | 'pending' | 'none';

export interface PublishStep {
  node: 'L4' | 'L5';
  step: string;
  result: 'ok' | 'failed' | 'skipped' | 'compensated';
  ms: number;
  detail?: string;
}

export interface PublishResult {
  status: PublishStatus;
  correlationId: string;
  product: Product;
  image: { status: ImageStatus; url: string | null; reason?: string };
  steps: PublishStep[];
}

const reasonOf = (error: unknown) =>
  error instanceof Error ? error.message : String(error);

/**
 * ORQUESTADOR de "Publicar producto con imagen" (L3).
 *
 * Compone dos capacidades en una sola operación:
 *   L5 (servidor de archivos) guarda la imagen;
 *   L4 (PostgreSQL) guarda el producto y los metadatos del archivo.
 *
 * Secuencia: validar → categoría en L4 → imagen en L5 → producto + metadatos en L4 (una transacción).
 * - L5 falla o no responde: el producto se publica SIN imagen (degraded) y la imagen queda pendiente.
 * - L4 falla: no se publica nada; si la imagen ya estaba en L5 se borra (compensación) y se responde 503.
 */
@Injectable()
export class ProductPublisherService {
  private readonly logger = new Logger('PublicarProducto');

  constructor(
    private readonly dataSource: DataSource,
    private readonly files: FilesService,
    private readonly categories: CategoriesService,
    private readonly products: ProductsService,
  ) {}

  async publish(
    dto: PublishProductDto,
    file: Express.Multer.File | undefined,
    userId: number,
    correlationId: string,
  ): Promise<PublishResult> {
    const steps: PublishStep[] = [];
    const log = (text: string) => this.logger.log(`[${correlationId}] ${text}`);
    log(
      `inicio: "${dto.name}" · ${file ? `imagen ${file.originalname} (${file.size} B)` : 'sin imagen'}`,
    );

    // 1. Regla de negocio ANTES de llamar a L5: la categoría debe existir.
    if (dto.categoryId !== undefined) {
      const categoryId = dto.categoryId;
      await this.step(steps, 'L4', 'Validar categoría', correlationId, () =>
        this.categories.ensureExists(categoryId),
      );
    }

    // 2. L5: guardar la imagen. Si falla, el flujo continúa sin imagen (degradación).
    let stored: StoredFile | null = null;
    let reason: string | undefined;
    if (file) {
      const start = performance.now();
      try {
        stored = await this.files.store(file, correlationId);
        steps.push({
          node: 'L5',
          step: 'Guardar imagen',
          result: 'ok',
          ms: this.elapsed(start),
        });
        log(`L5 guardar imagen: ok · clave ${stored.storageKey}`);
      } catch (error) {
        reason = reasonOf(error);
        steps.push({
          node: 'L5',
          step: 'Guardar imagen',
          result: 'failed',
          ms: this.elapsed(start),
          detail: reason,
        });
        this.logger.warn(
          `[${correlationId}] L5 guardar imagen: falló (${reason}); se publica sin imagen`,
        );
      }
    } else {
      steps.push({
        node: 'L5',
        step: 'Guardar imagen',
        result: 'skipped',
        ms: 0,
        detail: 'No se envió imagen',
      });
    }

    // 3. L4: producto y metadatos del archivo en UNA transacción (todo o nada).
    const start = performance.now();
    let productId: number;
    try {
      productId = await this.dataSource.transaction(async (manager) => {
        const { categoryId, ...data } = dto;
        const product = await manager.save(
          manager.create(Product, {
            ...data,
            categoryId: categoryId ?? null,
            category: categoryId ? ({ id: categoryId } as Category) : null,
            imageUrl: stored?.url ?? null,
          }),
        );
        if (stored && file) {
          await manager.save(
            this.files.createRecord(
              stored,
              file,
              { type: 'product', id: product.id },
              userId,
            ),
          );
        }
        return product.id;
      });
      steps.push({
        node: 'L4',
        step: 'Guardar producto y metadatos',
        result: 'ok',
        ms: this.elapsed(start),
      });
      log(`L4 guardar producto y metadatos: ok · producto #${productId}`);
    } catch (error) {
      steps.push({
        node: 'L4',
        step: 'Guardar producto y metadatos',
        result: 'failed',
        ms: this.elapsed(start),
        detail: reasonOf(error),
      });
      this.logger.error(
        `[${correlationId}] L4 guardar producto: falló (${reasonOf(error)})`,
      );
      let compensation = '';
      if (stored) {
        const removed = await this.files.remove(
          stored.storageKey,
          correlationId,
        );
        steps.push({
          node: 'L5',
          step: 'Borrar imagen (compensación)',
          result: removed ? 'compensated' : 'failed',
          ms: 0,
          detail: stored.storageKey,
        });
        compensation = removed
          ? ' La imagen ya guardada en L5 se borró (compensación).'
          : ` No se pudo borrar la imagen ${stored.storageKey} de L5: queda huérfana.`;
        this.logger.warn(
          `[${correlationId}] compensación en L5: ${removed ? 'imagen borrada' : 'no se pudo borrar'} (${stored.storageKey})`,
        );
      }
      throw new ServiceUnavailableException({
        statusCode: 503,
        message: `La base de datos (L4) no está disponible: el producto no se publicó.${compensation}`,
        error: 'Service Unavailable',
        status: 'failed',
        correlationId,
        steps,
      });
    }

    // 4. Respuesta compuesta: producto + resultado de cada servicio.
    const status: PublishStatus = file && !stored ? 'degraded' : 'completed';
    log(`fin: ${status}`);
    return {
      status,
      correlationId,
      product: await this.products.findOne(productId),
      image: {
        status: stored ? 'stored' : file ? 'pending' : 'none',
        url: stored?.url ?? null,
        ...(reason && { reason }),
      },
      steps,
    };
  }

  /** Paso en L4 que, si falla por la base de datos, detiene el flujo con 503. */
  private async step(
    steps: PublishStep[],
    node: PublishStep['node'],
    name: string,
    correlationId: string,
    action: () => Promise<unknown>,
  ) {
    const start = performance.now();
    try {
      await action();
      steps.push({ node, step: name, result: 'ok', ms: this.elapsed(start) });
    } catch (error) {
      if (error instanceof HttpException) throw error; // p. ej. 400 categoría inexistente
      this.logger.error(
        `[${correlationId}] ${node} ${name}: falló (${reasonOf(error)})`,
      );
      throw new ServiceUnavailableException({
        statusCode: 503,
        message:
          'La base de datos (L4) no está disponible: el producto no se publicó.',
        error: 'Service Unavailable',
        status: 'failed',
        correlationId,
        steps: [
          ...steps,
          {
            node,
            step: name,
            result: 'failed',
            ms: this.elapsed(start),
            detail: reasonOf(error),
          },
        ],
      });
    }
  }

  private elapsed(start: number) {
    return Math.round(performance.now() - start);
  }
}
