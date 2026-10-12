import { IsNotEmpty, IsOptional, IsString, MaxLength } from 'class-validator';

export class CreateCategoryDto {
  /** @example 'Electrónica' */
  @IsString()
  @IsNotEmpty()
  @MaxLength(80)
  name: string;

  /** @example 'Audio, computación y accesorios' */
  @IsOptional()
  @IsString()
  description?: string;
}
