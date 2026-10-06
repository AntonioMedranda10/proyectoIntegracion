import {
  Column,
  CreateDateColumn,
  Entity,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  type Relation,
} from 'typeorm';
import { User } from '../users/user.entity.js';

/** Metadatos de un archivo guardado en el servidor de archivos (L5). */
@Entity({ name: 'files', schema: 'storage' })
export class FileRecord {
  @PrimaryGeneratedColumn()
  id: number;

  @Column({ name: 'storage_key' })
  storageKey: string;

  @Column()
  url: string;

  @Column({ name: 'original_name' })
  originalName: string;

  @Column({ name: 'mime_type' })
  mimeType: string;

  @Column()
  size: number;

  /**
   * Objeto del dominio al que pertenece (por ejemplo 'product').
   * Es una referencia genérica, por eso no tiene llave foránea.
   */
  @Column({ name: 'owner_type', length: 30 })
  ownerType: string;

  @Column({ name: 'owner_id' })
  ownerId: number;

  @ManyToOne(() => User, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'uploaded_by' })
  uploader: Relation<User>;

  @Column({ name: 'uploaded_by' })
  uploadedBy: number;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}
