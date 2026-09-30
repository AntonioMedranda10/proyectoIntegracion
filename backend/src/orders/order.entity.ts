import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  OneToMany,
  PrimaryGeneratedColumn,
  type Relation,
} from 'typeorm';
import { numericTransformer } from '../common/numeric.transformer.js';
import { Product } from '../products/product.entity.js';

export type OrderStatus = 'pending' | 'paid' | 'cancelled';

@Entity('orders')
export class Order {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'user_id' })
  userId: number;

  @Column({ type: 'varchar', length: 20, default: 'pending' })
  status: OrderStatus;

  @Column({
    type: 'numeric',
    precision: 12,
    scale: 2,
    transformer: numericTransformer,
  })
  total: number;

  @OneToMany(() => OrderItem, (item) => item.order, {
    cascade: true,
    eager: true,
  })
  items: Relation<OrderItem[]>;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}

@Entity('order_items')
export class OrderItem {
  @PrimaryGeneratedColumn()
  id: number;

  @ManyToOne(() => Order, (order) => order.items, { onDelete: 'CASCADE' })
  order: Relation<Order>;

  // Si el producto se elimina, el pedido conserva el nombre y precio de la compra.
  @ManyToOne(() => Product, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'product_id' })
  product: Relation<Product> | null;

  @Column({ name: 'product_id', type: 'int', nullable: true })
  productId: number | null;

  @Column({ name: 'product_name' })
  productName: string;

  @Column({
    name: 'unit_price',
    type: 'numeric',
    precision: 10,
    scale: 2,
    transformer: numericTransformer,
  })
  unitPrice: number;

  @Column({ type: 'int' })
  quantity: number;
}
