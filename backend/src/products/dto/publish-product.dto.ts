import { Type } from 'class-transformer';
import {
  IsInt,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';

/**
 * Datos de "Publicar producto con imagen". Llegan como multipart/form-data
 * (junto con el archivo), por eso los números vienen como texto y se convierten.
 */
export class PublishProductDto {
  /** @example 'Webcam Full HD' */
  @IsString()
  @IsNotEmpty()
  @MaxLength(120)
  name: string;

  /** @example 'Micrófono incorporado' */
  @IsOptional()
  @IsString()
  description?: string;

  /** @example 45.9 */
  @Type(() => Number)
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  price: number;

  /** @example 12 */
  @Type(() => Number)
  @IsInt()
  @Min(0)
  stock: number;

  /** Id de la categoría (opcional). @example 1 */
  @IsOptional()
  @Type(() => Number)
  @IsInt()
  categoryId?: number;
}
