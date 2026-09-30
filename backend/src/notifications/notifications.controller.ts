import { Controller, Get, Param, ParseIntPipe, Patch } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import {
  CurrentUser,
  type AuthUser,
} from '../common/current-user.decorator.js';
import { NotificationsService } from './notifications.service.js';

@ApiTags('notifications')
@Controller('notifications')
@Auth()
export class NotificationsController {
  constructor(private readonly notificationsService: NotificationsService) {}

  @Get()
  findMine(@CurrentUser() user: AuthUser) {
    return this.notificationsService.findForUser(user.id);
  }

  @Patch(':id/read')
  markAsRead(
    @Param('id', ParseIntPipe) id: number,
    @CurrentUser() user: AuthUser,
  ) {
    return this.notificationsService.markAsRead(id, user.id);
  }
}
