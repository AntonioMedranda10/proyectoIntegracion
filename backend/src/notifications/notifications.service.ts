import { Injectable, NotFoundException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { Notification } from './notification.entity.js';

@Injectable()
export class NotificationsService {
  constructor(
    @InjectRepository(Notification)
    private readonly notifications: Repository<Notification>,
  ) {}

  notify(userId: number, message: string) {
    return this.notifications.save(
      this.notifications.create({ userId, message }),
    );
  }

  findForUser(userId: number) {
    return this.notifications.find({
      where: { userId },
      order: { id: 'DESC' },
    });
  }

  async markAsRead(id: number, userId: number) {
    const notification = await this.notifications.findOneBy({ id, userId });
    if (!notification)
      throw new NotFoundException(`Notificación ${id} no encontrada`);
    notification.read = true;
    return this.notifications.save(notification);
  }
}
