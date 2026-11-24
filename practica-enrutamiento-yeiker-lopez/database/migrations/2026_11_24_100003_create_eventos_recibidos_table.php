<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro del recorrido de cada evento: qué decidió el filtro, a dónde lo
 * envió el enrutador y qué hizo el consumidor. Distingue el rechazo por regla
 * de negocio (filtrado) del fallo de transporte (error_transporte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_recibidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evento_id')->index();       // "id" del mensaje (puede repetirse si llega duplicado)
            $table->string('correlation_id', 100)->index();
            $table->string('tipo', 40);
            $table->string('prioridad', 10);
            $table->string('decision', 20);                         // enrutado | no_enrutable | filtrado | error_transporte
            $table->string('regla', 5);                              // R1..R4 (enrutador) o F1..F2 (filtro)
            $table->string('destino', 20)->nullable();
            $table->string('cola', 60)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->string('estado_consumo', 15)->nullable();       // en_cola | procesado | fallido
            $table->string('consumidor', 20)->nullable();
            $table->string('resultado', 255)->nullable();
            $table->timestamp('recibido_en', 3);
            $table->timestamp('procesado_en', 3)->nullable();
            $table->timestamps(3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_recibidos');
    }
};
