import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from 'typeorm';
import { numericTransformer } from '../common/numeric.transformer.js';

export type PaymentMethod = 'card' | 'cash' | 'transfer';

@Entity('payments')
export class Payment {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'order_id' })
  orderId: number;

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
