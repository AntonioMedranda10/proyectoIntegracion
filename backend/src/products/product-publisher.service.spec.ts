import {
  BadRequestException,
  ServiceUnavailableException,
} from '@nestjs/common';
import type { DataSource } from 'typeorm';
import type { FilesService } from '../files/files.service.js';
import type { CategoriesService } from './categories.service.js';
import { ProductPublisherService } from './product-publisher.service.js';
import type { ProductsService } from './products.service.js';

const imagen = {
  originalname: 'webcam.png',
  mimetype: 'image/png',
  size: 2048,
  buffer: Buffer.from('png'),
} as Express.Multer.File;

const datos = { name: 'Webcam Full HD', price: 45.9, stock: 12 };

/** Arma el orquestador con dobles de L4 (DataSource) y L5 (FilesService). */
function crear({ l4Cae = false, l5Cae = false } = {}) {
  const guardados: Record<string, unknown>[] = [];
  const manager = {
    create: (_entidad: unknown, valores: Record<string, unknown>) => ({
      ...valores,
    }),
    save: vi.fn(async (valores: Record<string, unknown>) => {
      guardados.push(valores);
      return { id: 7, ...valores };
    }),
  };
  const dataSource = {
    transaction: vi.fn(
      async (trabajo: (m: typeof manager) => Promise<number>) => {
        if (l4Cae) throw new Error('connect ECONNREFUSED 127.0.0.1:5434');
        return trabajo(manager);
      },
    ),
  };
  const files = {
    store: vi.fn(async () => {
      if (l5Cae)
        throw new ServiceUnavailableException(
          'El servidor de archivos no está disponible',
        );
      return { storageKey: 'abc.png', url: '/files/abc.png' };
    }),
    createRecord: vi.fn((stored: object) => ({
      ...stored,
      ownerType: 'product',
    })),
    remove: vi.fn(async () => true),
  };
  const categories = {
    ensureExists: vi.fn(async (id: number) => {
      if (id !== 1)
        throw new BadRequestException(`La categoría ${id} no existe`);
    }),
  };
  const products = {
    findOne: vi.fn(async (id: number) => ({ id, name: datos.name })),
  };
  const orquestador = new ProductPublisherService(
    dataSource as unknown as DataSource,
    files as unknown as FilesService,
    categories as unknown as CategoriesService,
    products as unknown as ProductsService,
  );
  return { orquestador, files, categories, dataSource, guardados };
}

describe('ProductPublisherService (orquestación L3 → L5 + L4)', () => {
  it('completed: guarda la imagen en L5 y el producto con sus metadatos en L4', async () => {
    const { orquestador, files, guardados } = crear();

    const r = await orquestador.publish(datos, imagen, 1, 'corr-1');

    expect(r.status).toBe('completed');
    expect(r.image).toEqual({ status: 'stored', url: '/files/abc.png' });
    expect(files.store).toHaveBeenCalledWith(imagen, 'corr-1'); // propaga la correlación a L5
    expect(guardados[0]).toMatchObject({
      name: 'Webcam Full HD',
      imageUrl: '/files/abc.png',
    });
    expect(guardados[1]).toMatchObject({ storageKey: 'abc.png' }); // metadatos en la misma transacción
    expect(r.steps.map((s) => `${s.node}:${s.result}`)).toEqual([
      'L5:ok',
      'L4:ok',
    ]);
  });

  it('degraded: si L5 no responde, publica el producto sin imagen', async () => {
    const { orquestador, guardados } = crear({ l5Cae: true });

    const r = await orquestador.publish(datos, imagen, 1, 'corr-2');

    expect(r.status).toBe('degraded');
    expect(r.image).toEqual({
      status: 'pending',
      url: null,
      reason: 'El servidor de archivos no está disponible',
    });
    expect(guardados).toHaveLength(1); // solo el producto: no hay metadatos de archivo
    expect(guardados[0]).toMatchObject({ imageUrl: null });
    expect(r.steps.map((s) => `${s.node}:${s.result}`)).toEqual([
      'L5:failed',
      'L4:ok',
    ]);
  });

  it('failed: si L4 cae después de L5, borra la imagen (compensación) y responde 503', async () => {
    const { orquestador, files } = crear({ l4Cae: true });

    const error = await orquestador
      .publish(datos, imagen, 1, 'corr-3')
      .catch((e) => e);

    expect(error).toBeInstanceOf(ServiceUnavailableException);
    expect(error.getResponse()).toMatchObject({
      status: 'failed',
      correlationId: 'corr-3',
      message: expect.stringContaining('se borró (compensación)'),
    });
    expect(files.remove).toHaveBeenCalledWith('abc.png', 'corr-3');
  });

  it('valida la categoría en L4 antes de llamar a L5', async () => {
    const { orquestador, files } = crear();

    await expect(
      orquestador.publish({ ...datos, categoryId: 99 }, imagen, 1, 'corr-4'),
    ).rejects.toBeInstanceOf(BadRequestException);
    expect(files.store).not.toHaveBeenCalled();
  });

  it('sin imagen: no llama a L5 y el flujo queda completed', async () => {
    const { orquestador, files } = crear();

    const r = await orquestador.publish(
      { ...datos, categoryId: 1 },
      undefined,
      1,
      'corr-5',
    );

    expect(r.status).toBe('completed');
    expect(r.image.status).toBe('none');
    expect(files.store).not.toHaveBeenCalled();
    expect(r.steps.map((s) => `${s.node}:${s.result}`)).toEqual([
      'L4:ok',
      'L5:skipped',
      'L4:ok',
    ]);
  });
});
