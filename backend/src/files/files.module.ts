import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { FileRecord } from './file-record.entity.js';
import { FilesService } from './files.service.js';

@Module({
  imports: [TypeOrmModule.forFeature([FileRecord])],
  providers: [FilesService],
  exports: [FilesService],
})
export class FilesModule {}
