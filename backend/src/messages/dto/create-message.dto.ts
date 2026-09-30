import { IsNotEmpty, IsString, MaxLength } from 'class-validator';

export class CreateMessageDto {
  /** @example 'Consulta sobre mi pedido' */
  @IsString()
  @IsNotEmpty()
  @MaxLength(150)
  subject: string;

  /** @example '¿Cuándo llega mi pedido #3?' */
  @IsString()
  @IsNotEmpty()
  content: string;
}
