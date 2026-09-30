import {
  ConflictException,
  Injectable,
  Logger,
  OnApplicationBootstrap,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InjectRepository } from '@nestjs/typeorm';
import { hash } from 'bcryptjs';
import { Repository } from 'typeorm';
import { Role, User } from './user.entity.js';

@Injectable()
export class UsersService implements OnApplicationBootstrap {
  private readonly logger = new Logger(UsersService.name);

  constructor(
    @InjectRepository(User) private readonly users: Repository<User>,
    private readonly config: ConfigService,
  ) {}

  /** Crea el administrador de prueba (solo en la instancia que sincroniza la base). */
  async onApplicationBootstrap() {
    if (this.config.get('DB_SYNC') !== 'true') return;
    const email = this.config.get<string>('ADMIN_EMAIL', 'admin@tienda.com');
    if (await this.users.existsBy({ email })) return;
    await this.create(
      'Administrador',
      email,
      this.config.get<string>('ADMIN_PASSWORD', 'Admin123!'),
      'admin',
    );
    this.logger.log(`Usuario administrador creado: ${email}`);
  }

  async create(
    name: string,
    email: string,
    password: string,
    role: Role = 'customer',
  ) {
    const normalized = email.toLowerCase();
    if (await this.users.existsBy({ email: normalized })) {
      throw new ConflictException('El email ya está registrado');
    }
    const user = await this.users.save(
      this.users.create({
        name,
        email: normalized,
        passwordHash: await hash(password, 10),
        role,
      }),
    );
    const { passwordHash: _, ...publicUser } = user;
    return publicUser as User;
  }

  findByEmailWithPassword(email: string) {
    return this.users
      .createQueryBuilder('user')
      .addSelect('user.passwordHash')
      .where('user.email = :email', { email: email.toLowerCase() })
      .getOne();
  }

  findById(id: number) {
    return this.users.findOneBy({ id });
  }

  findAll() {
    return this.users.find({ order: { id: 'ASC' } });
  }
}
