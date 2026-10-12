import { Controller, Get } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import { AuditService } from './audit.service.js';

@ApiTags('audit (admin)')
@Controller('audit')
@Auth('admin')
export class AuditController {
  constructor(private readonly auditService: AuditService) {}

  /** Últimas 100 operaciones que modificaron datos. */
  @Get()
  findRecent() {
    return this.auditService.findRecent();
  }
}
