import { Injectable, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { ActivityLog } from './activity-log.entity.js';

export type ActivityEntry = Pick<
  ActivityLog,
  'userId' | 'method' | 'path' | 'statusCode' | 'instance' | 'ipAddress'
>;

@Injectable()
export class AuditService {
  private readonly logger = new Logger(AuditService.name);

  constructor(
    @InjectRepository(ActivityLog)
    private readonly activity: Repository<ActivityLog>,
  ) {}

  /** Guarda el registro sin interrumpir la petición si la auditoría falla. */
  async record(entry: ActivityEntry) {
    try {
      await this.activity.insert(entry);
    } catch (error) {
      this.logger.warn(`No se pudo registrar la actividad: ${error}`);
    }
  }

  findRecent(limit = 100) {
    return this.activity.find({ order: { id: 'DESC' }, take: limit });
  }
}
