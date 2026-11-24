<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos mínimos de la tienda que necesitan los consumidores. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->decimal('precio', 10, 2);
            $table->unsignedInteger('stock');
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('cliente', 120);
            $table->decimal('total', 10, 2);
            $table->string('estado', 20);
            $table->timestamps();
        });

        Schema::create('pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_items');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('productos');
    }
};
