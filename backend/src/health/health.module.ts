import { Module } from '@nestjs/common';
import { FilesModule } from '../files/files.module.js';
import { HealthController } from './health.controller.js';

@Module({
  imports: [FilesModule],
  controllers: [HealthController],
})
export class HealthModule {}
