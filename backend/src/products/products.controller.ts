import {
  BadRequestException,
  Body,
  Controller,
  Delete,
  Param,
  ParseIntPipe,
  Patch,
  Post,
  UploadedFile,
  UseInterceptors,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { ApiBody, ApiConsumes, ApiTags } from '@nestjs/swagger';
import { Auth } from '../common/auth.decorator.js';
import { CorrelationId } from '../common/correlation-id.js';
import {
  CurrentUser,
  type AuthUser,
} from '../common/current-user.decorator.js';
import { ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE } from '../files/files.service.js';
import { CreateProductDto } from './dto/create-product.dto.js';
import { PublishProductDto } from './dto/publish-product.dto.js';
import { UpdateProductDto } from './dto/update-product.dto.js';
import { ProductPublisherService } from './product-publisher.service.js';
import { ProductsService } from './products.service.js';

/** Recibe la imagen en memoria (campo "file") y rechaza lo que no sea JPG, PNG o WEBP de hasta 5 MB. */
const imageInterceptor = () =>
  FileInterceptor('file', {
    limits: { fileSize: MAX_FILE_SIZE },
    fileFilter: (_req, file, callback) =>
      ALLOWED_IMAGE_TYPES.includes(file.mimetype)
        ? callback(null, true)
        : callback(
            new BadRequestException(
              'Solo se permiten imágenes JPG, PNG o WEBP',
            ),
            false,
          ),
  });

@ApiTags('products (admin)')
@Controller('products')
@Auth('admin')
export class ProductsController {
  constructor(
    private readonly productsService: ProductsService,
    private readonly publisher: ProductPublisherService,
  ) {}

  /**
   * Publicar producto con imagen en una sola operación (orquestación L3 → L5 + L4).
   * 201 status "completed": producto e imagen guardados.
   * 201 status "degraded": L5 no respondió; el producto se publicó sin imagen.
   * 503: L4 no disponible; no se publicó nada (si la imagen llegó a L5, se borra).
   */
  @Post('publish')
  @ApiConsumes('multipart/form-data')
  @ApiBody({
    schema: {
      type: 'object',
      properties: {
        name: { type: 'string', example: 'Webcam Full HD' },
        description: { type: 'string', example: 'Micrófono incorporado' },
        price: { type: 'number', example: 45.9 },
        stock: { type: 'integer', example: 12 },
        categoryId: { type: 'integer', example: 1 },
        file: { type: 'string', format: 'binary' },
      },
      required: ['name', 'price', 'stock'],
    },
  })
  @UseInterceptors(imageInterceptor())
  publish(
    @Body() dto: PublishProductDto,
    @UploadedFile() file: Express.Multer.File | undefined,
    @CurrentUser() user: AuthUser,
    @CorrelationId() correlationId: string,
  ) {
    return this.publisher.publish(dto, file, user.id, correlationId);
  }

  @Post()
  create(@Body() dto: CreateProductDto) {
    return this.productsService.create(dto);
  }

  @Patch(':id')
  update(@Param('id', ParseIntPipe) id: number, @Body() dto: UpdateProductDto) {
    return this.productsService.update(id, dto);
  }

  @Delete(':id')
  remove(@Param('id', ParseIntPipe) id: number) {
    return this.productsService.remove(id);
  }

  @Post(':id/image')
  @ApiConsumes('multipart/form-data')
  @ApiBody({
    schema: {
      type: 'object',
      properties: { file: { type: 'string', format: 'binary' } },
      required: ['file'],
    },
  })
  @UseInterceptors(imageInterceptor())
  uploadImage(
    @Param('id', ParseIntPipe) id: number,
    @UploadedFile() file: Express.Multer.File | undefined,
    @CurrentUser() user: AuthUser,
  ) {
    return this.productsService.setImage(id, file, user.id);
  }
}
