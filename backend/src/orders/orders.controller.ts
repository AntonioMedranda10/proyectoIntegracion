import { Body, Controller, Get, Post } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import {
  CurrentUser,
  type AuthUser,
} from '../common/current-user.decorator.js';
import { CreateOrderDto } from './dto/create-order.dto.js';
import { OrdersService } from './orders.service.js';

@ApiTags('orders')
@Controller('orders')
export class OrdersController {
  constructor(private readonly ordersService: OrdersService) {}

  @Post()
  @Auth()
  create(@CurrentUser() user: AuthUser, @Body() dto: CreateOrderDto) {
    return this.ordersService.create(user.id, dto);
  }

  @Get('my')
  @Auth()
  findMine(@CurrentUser() user: AuthUser) {
    return this.ordersService.findForUser(user.id);
  }

  @Get()
  @Auth('admin')
  findAll() {
    return this.ordersService.findAll();
  }
}
