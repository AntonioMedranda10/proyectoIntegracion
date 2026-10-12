import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { DataSource, Repository } from 'typeorm';
import { NotificationsService } from '../notifications/notifications.service.js';
import { Product } from '../products/product.entity.js';
import type { CreateOrderDto } from './dto/create-order.dto.js';
import { Order, OrderItem } from './order.entity.js';

@Injectable()
export class OrdersService {
  constructor(
    @InjectRepository(Order) private readonly orders: Repository<Order>,
    private readonly dataSource: DataSource,
    private readonly notificationsService: NotificationsService,
  ) {}

  async create(userId: number, dto: CreateOrderDto) {
    const orderId = await this.dataSource.transaction(async (manager) => {
      const items: OrderItem[] = [];
      let total = 0;
      for (const { productId, quantity } of dto.items) {
        // Bloquea la fila para que dos instancias no vendan el mismo stock a la vez.
        // Sin la categoría: PostgreSQL no permite FOR UPDATE sobre un LEFT JOIN.
        const product = await manager.findOne(Product, {
          where: { id: productId },
          lock: { mode: 'pessimistic_write' },
          loadEagerRelations: false,
        });
        if (!product)
          throw new NotFoundException(`Producto ${productId} no encontrado`);
        if (product.stock < quantity) {
          throw new BadRequestException(
            `Stock insuficiente para "${product.name}"`,
          );
        }
        product.stock -= quantity;
        await manager.save(product);
        total += product.price * quantity;
        items.push(
          manager.create(OrderItem, {
            productId: product.id,
            productName: product.name,
            unitPrice: product.price,
            quantity,
          }),
        );
      }
      const order = manager.create(Order, {
        userId,
        items,
        total: Math.round(total * 100) / 100,
      });
      return (await manager.save(order)).id;
    });

    await this.notificationsService.notify(
      userId,
      `Tu pedido #${orderId} fue registrado`,
    );
    return this.findOne(orderId);
  }

  async findOne(id: number) {
    const order = await this.orders.findOneBy({ id });
    if (!order) throw new NotFoundException(`Pedido ${id} no encontrado`);
    return order;
  }

  /** Pedido que pertenece al usuario indicado (para pagos). */
  async findOwned(id: number, userId: number) {
    const order = await this.findOne(id);
    if (order.userId !== userId)
      throw new ForbiddenException('El pedido no te pertenece');
    return order;
  }

  findForUser(userId: number) {
    return this.orders.find({ where: { userId }, order: { id: 'DESC' } });
  }

  findAll() {
    return this.orders.find({ order: { id: 'DESC' } });
  }

  /** Devuelve false si el pedido ya no estaba pendiente (evita pagarlo dos veces). */
  async markAsPaid(orderId: number) {
    const result = await this.orders.update(
      { id: orderId, status: 'pending' },
      { status: 'paid' },
    );
    return (result.affected ?? 0) > 0;
  }
}
