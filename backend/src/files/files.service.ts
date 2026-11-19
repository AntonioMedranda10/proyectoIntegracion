import { randomUUID } from 'node:crypto';
import { access, unlink, writeFile } from 'node:fs/promises';
import { basename, extname, join, resolve } from 'node:path';
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
import { CORRELATION_HEADER } from '../common/correlation-id.js';
import { FileRecord } from './file-record.entity.js';

export const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
export const MAX_FILE_SIZE = 5 * 1024 * 1024;

export interface StoredFile {
  storageKey: string;
  url: string;
}

export interface FileOwner {
  type: string;
  id: number;
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
  private readonly timeoutMs: number;

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
    // Tiempo máximo de espera a L5 al subir un archivo.
    this.timeoutMs = Number(config.get('FILES_TIMEOUT_MS', 10000));
  }

  async upload(
    file: Express.Multer.File | undefined,
    owner: FileOwner,
    uploadedBy: number,
  ) {
    if (!file)
      throw new BadRequestException(
        'Debes enviar un archivo en el campo "file"',
      );
    const stored = await this.store(file);
    return this.files.save(this.createRecord(stored, file, owner, uploadedBy));
  }

  /** Guarda el archivo en L5 (o en la carpeta local) sin tocar la base de datos. */
  store(file: Express.Multer.File, correlationId?: string) {
    return this.mode === 'remote'
      ? this.storeRemote(file, correlationId)
      : this.storeLocal(file);
  }

  /** Metadatos del archivo listos para guardarse (por ejemplo, dentro de una transacción). */
  createRecord(
    stored: StoredFile,
    file: Express.Multer.File,
    owner: FileOwner,
    uploadedBy: number,
  ) {
    return this.files.create({
      ...stored,
      originalName: file.originalname,
      mimeType: file.mimetype,
      size: file.size,
      ownerType: owner.type,
      ownerId: owner.id,
      uploadedBy,
    });
  }

  /**
   * Borra un archivo ya guardado (compensación cuando L4 no pudo registrar el producto).
   * Devuelve false si no se pudo: el archivo queda huérfano y se deja constancia en el log.
   */
  async remove(storageKey: string, correlationId?: string): Promise<boolean> {
    try {
      if (this.mode === 'local') {
        await unlink(join(this.uploadsDir, basename(storageKey)));
        return true;
      }
      const path = this.config.get<string>('FILES_DELETE_PATH', '/files');
      const response = await fetch(
        `${this.filesUrl}${path}/${encodeURIComponent(storageKey)}`,
        {
          method: 'DELETE',
          headers: this.headersForL5(correlationId),
          signal: AbortSignal.timeout(this.timeoutMs),
        },
      );
      return response.ok;
    } catch (error) {
      this.logger.error(`No se pudo borrar ${storageKey} en L5: ${error}`);
      return false;
    }
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

  /** L5 exige una clave compartida (x-api-key) y recibe el X-Correlation-ID de la operación. */
  private headersForL5(correlationId?: string) {
    const apiKey = this.config.get<string>('FILES_API_KEY');
    const headers: Record<string, string> = apiKey
      ? { 'x-api-key': apiKey }
      : {};
    if (correlationId) headers[CORRELATION_HEADER] = correlationId;
    return headers;
  }

  private async storeRemote(
    file: Express.Multer.File,
    correlationId?: string,
  ): Promise<StoredFile> {
    const form = new FormData();
    form.append(
      'file',
      new Blob([Uint8Array.from(file.buffer)], { type: file.mimetype }),
      file.originalname,
    );

    let response: Response;
    try {
      response = await fetch(
        this.filesUrl + this.config.get<string>('FILES_UPLOAD_PATH', '/upload'),
        {
          method: 'POST',
          body: form,
          headers: this.headersForL5(correlationId),
          signal: AbortSignal.timeout(this.timeoutMs),
        },
      );
    } catch (error) {
      const timeout = error instanceof Error && error.name === 'TimeoutError';
      this.logger.error(
        `No se pudo conectar con el servidor de archivos: ${error}`,
      );
      throw new ServiceUnavailableException(
        timeout
          ? `El servidor de archivos no respondió en ${this.timeoutMs / 1000} s`
          : 'El servidor de archivos no está disponible',
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
