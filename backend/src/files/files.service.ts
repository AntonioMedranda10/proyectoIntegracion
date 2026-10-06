import { randomUUID } from 'node:crypto';
import { access, writeFile } from 'node:fs/promises';
import { extname, join, resolve } from 'node:path';
import {
  BadGatewayException,
  BadRequestException,
  Injectable,
  Logger,
  ServiceUnavailableException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { FileRecord } from './file-record.entity.js';

export const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const MAX_FILE_SIZE = 5 * 1024 * 1024;

interface StoredFile {
  storageKey: string;
  url: string;
}

/**
 * Guarda archivos en L5 y registra sus metadatos en L4.
 * FILES_MODE=remote -> envía el archivo por HTTP al servidor de archivos (L5).
 * FILES_MODE=local  -> lo guarda en una carpeta local (solo para desarrollo, sin L5).
 */
@Injectable()
export class FilesService {
  private readonly logger = new Logger(FilesService.name);
  private readonly mode: 'local' | 'remote';
  private readonly filesUrl: string;
  private readonly uploadsDir: string;

  constructor(
    @InjectRepository(FileRecord)
    private readonly files: Repository<FileRecord>,
    private readonly config: ConfigService,
  ) {
    this.mode =
      config.get('FILES_MODE', 'local') === 'remote' ? 'remote' : 'local';
    this.filesUrl = config
      .get<string>('FILES_URL', 'http://192.168.10.15')
      .replace(/\/$/, '');
    this.uploadsDir = resolve(config.get<string>('UPLOADS_DIR', 'uploads'));
  }

  async upload(
    file: Express.Multer.File | undefined,
    owner: { type: string; id: number },
    uploadedBy: number,
  ) {
    if (!file)
      throw new BadRequestException(
        'Debes enviar un archivo en el campo "file"',
      );
    const stored =
      this.mode === 'remote'
        ? await this.storeRemote(file)
        : await this.storeLocal(file);
    return this.files.save(
      this.files.create({
        ...stored,
        originalName: file.originalname,
        mimeType: file.mimetype,
        size: file.size,
        ownerType: owner.type,
        ownerId: owner.id,
        uploadedBy,
      }),
    );
  }

  /** Estado del almacenamiento de archivos para /health. */
  async check(): Promise<'up' | 'down'> {
    try {
      if (this.mode === 'local') {
        await access(this.uploadsDir);
      } else {
        // Cualquier respuesta HTTP indica que L5 es alcanzable.
        await fetch(
          this.filesUrl + this.config.get<string>('FILES_HEALTH_PATH', '/'),
          {
            signal: AbortSignal.timeout(2000),
          },
        );
      }
      return 'up';
    } catch {
      return 'down';
    }
  }

  private async storeLocal(file: Express.Multer.File): Promise<StoredFile> {
    const storageKey = randomUUID() + extname(file.originalname).toLowerCase();
    await writeFile(join(this.uploadsDir, storageKey), file.buffer);
    return { storageKey, url: `/files/${storageKey}` };
  }

  private async storeRemote(file: Express.Multer.File): Promise<StoredFile> {
    const form = new FormData();
    form.append(
      'file',
      new Blob([Uint8Array.from(file.buffer)], { type: file.mimetype }),
      file.originalname,
    );

    // L5 exige una clave compartida en la cabecera x-api-key (si está configurada).
    const apiKey = this.config.get<string>('FILES_API_KEY');
    const headers: Record<string, string> = apiKey
      ? { 'x-api-key': apiKey }
      : {};

    let response: Response;
    try {
      response = await fetch(
        this.filesUrl + this.config.get<string>('FILES_UPLOAD_PATH', '/upload'),
        {
          method: 'POST',
          body: form,
          headers,
          signal: AbortSignal.timeout(10000),
        },
      );
    } catch (error) {
      this.logger.error(
        `No se pudo conectar con el servidor de archivos: ${error}`,
      );
      throw new ServiceUnavailableException(
        'El servidor de archivos no está disponible',
      );
    }
    if (response.status === 401 || response.status === 403) {
      this.logger.error('L5 rechazó la clave: revisa FILES_API_KEY en el .env');
      throw new BadGatewayException(
        'El servidor de archivos rechazó la clave de acceso',
      );
    }
    if (!response.ok) {
      this.logger.error(`El servidor de archivos respondió ${response.status}`);
      throw new BadGatewayException(
        'El servidor de archivos rechazó el archivo',
      );
    }

    // Se aceptan varios nombres de campo mientras se define el contrato con L5.
    const body = (await response.json()) as Record<string, string | undefined>;
    const storageKey =
      body.storage_key ?? body.storageKey ?? body.key ?? body.filename;
    if (!storageKey) {
      throw new BadGatewayException(
        'El servidor de archivos no devolvió una clave',
      );
    }
    return { storageKey, url: body.url ?? `/files/${storageKey}` };
  }
}
