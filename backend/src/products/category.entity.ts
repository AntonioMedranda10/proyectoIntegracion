import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from 'typeorm';

/** Categoría que agrupa productos del catálogo (por ejemplo "Electrónica"). */
@Entity({ name: 'categories', schema: 'catalog' })
export class Category {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ unique: true, length: 80 })
  name: string;

  @Column({ type: 'text', nullable: true })
  description: string | null;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}
