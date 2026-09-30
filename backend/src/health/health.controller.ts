import { hostname } from 'node:os';
import { Controller, Get } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ApiTags } from '@nestjs/swagger';
import { DataSource } from 'typeorm';
import { FilesService } from '../files/files.service.js';

@ApiTags('health')
@Controller('health')
export class HealthController {
  constructor(
    private readonly config: ConfigService,
    private readonly dataSource: DataSource,
    private readonly filesService: FilesService,
  ) {}

  /** Indica qué instancia respondió y si L4 (PostgreSQL) y L5 (archivos) están disponibles. */
  @Get()
  async check() {
    const [database, files] = await Promise.all([
      this.checkDatabase(),
      this.filesService.check(),
    ]);
    return {
      status: database === 'up' && files === 'up' ? 'ok' : 'degraded',
      instance: this.config.get('INSTANCE_NAME'),
      port: Number(this.config.get('PORT')),
      host: hostname(),
      database,
      files,
      timestamp: new Date().toISOString(),
    };
  }

  private async checkDatabase(): Promise<'up' | 'down'> {
    try {
      await this.dataSource.query('SELECT 1');
      return 'up';
    } catch {
      return 'down';
    }
  }
}
