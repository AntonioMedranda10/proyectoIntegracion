import {
  Column,
  CreateDateColumn,
  Entity,
  PrimaryGeneratedColumn,
} from 'typeorm';

/** Metadatos de un archivo guardado en el servidor de archivos (L5). */
@Entity('files')
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

  /** Objeto del dominio al que pertenece, por ejemplo 'product'. */
  @Column({ name: 'owner_type', length: 30 })
  ownerType: string;

  @Column({ name: 'owner_id' })
  ownerId: number;

  @Column({ name: 'uploaded_by' })
  uploadedBy: number;

  @CreateDateColumn({ name: 'created_at', type: 'timestamptz' })
  createdAt: Date;
}
