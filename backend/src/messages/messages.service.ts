import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import type { CreateMessageDto } from './dto/create-message.dto.js';
import { Message } from './message.entity.js';

@Injectable()
export class MessagesService {
  constructor(
    @InjectRepository(Message) private readonly messages: Repository<Message>,
  ) {}

  create(userId: number, dto: CreateMessageDto) {
    return this.messages.save(this.messages.create({ ...dto, userId }));
  }

  findForUser(userId: number) {
    return this.messages.find({ where: { userId }, order: { id: 'DESC' } });
  }

  findAll() {
    return this.messages.find({ order: { id: 'DESC' } });
  }
}
