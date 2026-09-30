import {
  IsEmail,
  IsNotEmpty,
  IsString,
  MaxLength,
  MinLength,
} from 'class-validator';

export class RegisterDto {
  /** @example 'Ana Pérez' */
  @IsString()
  @IsNotEmpty()
  @MaxLength(100)
  name: string;

  /** @example 'ana@correo.com' */
  @IsEmail({}, { message: 'El email no es válido' })
  email: string;

  /** @example '123456' */
  @IsString()
  @MinLength(6, { message: 'La contraseña debe tener al menos 6 caracteres' })
  password: string;
}
