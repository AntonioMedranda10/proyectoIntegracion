<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que escribe cada consumidor: así se comprueba que un mensaje llegó
 * únicamente al proceso que le corresponde.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Facturación
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos'); // un comprobante por pedido
            $table->decimal('monto', 10, 2);
            $table->string('metodo', 20)->nullable();
            $table->unsignedBigInteger('evento_id');
            $table->timestamp('emitido_en', 3);
        });

        // Logística
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos'); // una guía por pedido
            $table->string('guia', 30)->unique();
            $table->string('transportista', 60);
            $table->unsignedBigInteger('evento_id');
            $table->timestamp('registrado_en', 3);
        });

        // Inventario
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->integer('cantidad');
            $table->string('motivo', 30);
            $table->unsignedInteger('stock_resultante');
            $table->unsignedBigInteger('evento_id');
            $table->timestamp('registrado_en', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('envios');
        Schema::dropIfExists('comprobantes');
    }
};
