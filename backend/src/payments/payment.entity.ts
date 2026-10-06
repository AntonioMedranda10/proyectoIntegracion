import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  type Relation,
} from 'typeorm';
import { numericTransformer } from '../common/numeric.transformer.js';
import { Order } from '../orders/order.entity.js';
import { User } from '../users/user.entity.js';

export type PaymentMethod = 'card' | 'cash' | 'transfer';

@Entity({ name: 'payments', schema: 'sales' })
export class Payment {
  @PrimaryGeneratedColumn()
  id: number;

  // Cada pedido se paga una sola vez (relación 1 a 0..1).
  @ManyToOne(() => Order, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'order_id' })
  order: Relation<Order>;

  @Column({ name: 'order_id', unique: true })
  orderId: number;

  @ManyToOne(() => User, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'user_id' })
  user: Relation<User>;

  @Column({ name: 'user_id' })
  userId: number;

  @Column({
    type: 'numeric',
    precision: 12,
    scale: 2,
    transformer: numericTransformer,
  })
  amount: number;

  @Column({ type: 'varchar', length: 20 })
  method: PaymentMethod;

  /** Pago simulado: por ahora siempre se aprueba. */
  @Column({ type: 'varchar', length: 20, default: 'approved' })
  status: string;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}
