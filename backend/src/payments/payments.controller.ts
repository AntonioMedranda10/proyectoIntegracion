import { Body, Controller, Get, Post } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import {
  CurrentUser,
  type AuthUser,
} from '../common/current-user.decorator.js';
import { CreatePaymentDto } from './dto/create-payment.dto.js';
import { PaymentsService } from './payments.service.js';

@ApiTags('payments')
@Controller('payments')
@Auth()
export class PaymentsController {
  constructor(private readonly paymentsService: PaymentsService) {}

  @Post()
  pay(@CurrentUser() user: AuthUser, @Body() dto: CreatePaymentDto) {
    return this.paymentsService.pay(user.id, dto);
  }

  @Get('my')
  findMine(@CurrentUser() user: AuthUser) {
    return this.paymentsService.findForUser(user.id);
  }
}
