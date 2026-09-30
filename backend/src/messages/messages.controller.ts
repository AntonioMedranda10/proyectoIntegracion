import { Body, Controller, Get, Post } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import {
  CurrentUser,
  type AuthUser,
} from '../common/current-user.decorator.js';
import { CreateMessageDto } from './dto/create-message.dto.js';
import { MessagesService } from './messages.service.js';

@ApiTags('messages')
@Controller('messages')
export class MessagesController {
  constructor(private readonly messagesService: MessagesService) {}

  @Post()
  @Auth()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateMessageDto) {
    return this.messagesService.create(user.id, dto);
  }

  @Get('my')
  @Auth()
  findMine(@CurrentUser() user: AuthUser) {
    return this.messagesService.findForUser(user.id);
  }

  @Get()
  @Auth('admin')
  findAll() {
    return this.messagesService.findAll();
  }
}
