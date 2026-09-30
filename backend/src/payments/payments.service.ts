import { BadRequestException, Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { NotificationsService } from '../notifications/notifications.service.js';
import { OrdersService } from '../orders/orders.service.js';
import type { CreatePaymentDto } from './dto/create-payment.dto.js';
import { Payment } from './payment.entity.js';

@Injectable()
export class PaymentsService {
  constructor(
    @InjectRepository(Payment) private readonly payments: Repository<Payment>,
    private readonly ordersService: OrdersService,
    private readonly notificationsService: NotificationsService,
  ) {}

  async pay(userId: number, dto: CreatePaymentDto) {
    const order = await this.ordersService.findOwned(dto.orderId, userId);
    if (!(await this.ordersService.markAsPaid(order.id))) {
      throw new BadRequestException(
        `El pedido #${order.id} no está pendiente de pago`,
      );
    }
    const payment = await this.payments.save(
      this.payments.create({
        orderId: order.id,
        userId,
        amount: order.total,
        method: dto.method,
      }),
    );
    await this.notificationsService.notify(
      userId,
      `Pago del pedido #${order.id} aprobado`,
    );
    return payment;
  }

  findForUser(userId: number) {
    return this.payments.find({ where: { userId }, order: { id: 'DESC' } });
  }
}
