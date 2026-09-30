import {
  IsInt,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
  MaxLength,
  Min,
} from 'class-validator';

export class CreateProductDto {
  /** @example 'Mouse gamer' */
  @IsString()
  @IsNotEmpty()
  @MaxLength(120)
  name: string;

  /** @example 'RGB, 6 botones' */
  @IsOptional()
  @IsString()
  description?: string;

  /** @example 35.5 */
  @IsNumber({ maxDecimalPlaces: 2 })
  @Min(0)
  price: number;

  /** @example 20 */
  @IsInt()
  @Min(0)
  stock: number;
}
