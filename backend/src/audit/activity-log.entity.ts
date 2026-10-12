import {
  Column,
  CreateDateColumn,
  Entity,
  Index,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  type Relation,
} from 'typeorm';
import { User } from '../users/user.entity.js';

/** Registro de cada operación que modifica datos: quién, qué, cuándo y qué instancia la atendió. */
@Entity({ name: 'activity_log', schema: 'audit' })
export class ActivityLog {
  @PrimaryGeneratedColumn()
  id: number;

  // Si el usuario se elimina, el registro se conserva sin usuario.
  @ManyToOne(() => User, { nullable: true, onDelete: 'SET NULL' })
  @JoinColumn({ name: 'user_id' })
  user: Relation<User> | null;

  /** null cuando la operación no requiere sesión (registro o login). */
  @Column({ name: 'user_id', type: 'int', nullable: true })
  userId: number | null;

  @Column({ length: 10 })
  method: string;

  @Column({ length: 255 })
  path: string;

  @Column({ name: 'status_code', type: 'int' })
  statusCode: number;

  /** Instancia del backend que atendió la petición (backend-1 o backend-2). */
  @Column({ length: 50 })
  instance: string;

  @Column({ name: 'ip_address', type: 'varchar', length: 64, nullable: true })
  ipAddress: string | null;

  @Index()
  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}
