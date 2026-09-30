import { IsEmail, IsNotEmpty, IsString } from 'class-validator';

export class LoginDto {
  /** @example 'admin@tienda.com' */
  @IsEmail({}, { message: 'El email no es válido' })
  email: string;

  /** @example 'Admin123!' */
  @IsString()
  @IsNotEmpty()
  password: string;
}
