import { IsIn, IsInt } from 'class-validator';
import type { PaymentMethod } from '../payment.entity.js';

export class CreatePaymentDto {
  /** @example 1 */
  @IsInt()
  orderId: number;

  /** @example 'card' */
  @IsIn(['card', 'cash', 'transfer'])
  method: PaymentMethod;
}
