<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de estados: una fila por mensaje. La escribe el productor (en_cola)
 * y la actualiza el consumidor (procesando → enviada | fallida). Así se puede
 * consultar cómo terminó cada operación por message_id o por correlation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_pedido', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('correlation_id', 100)->index();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->string('evento', 40);
            $table->string('modo', 12);             // asincrono | sincrono
            $table->string('estado', 20);           // en_cola | procesando | enviada | fallida | no_publicada
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('destinatario', 160)->nullable(); // correo ENMASCARADO, nunca completo
            $table->string('detalle', 255)->nullable();      // motivo del fallo, si lo hubo
            $table->timestamp('recibido_en', 3);             // cuando la API recibió la petición
            $table->timestamp('procesado_en', 3)->nullable(); // cuando terminó el procesamiento
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_pedido');
    }
};
