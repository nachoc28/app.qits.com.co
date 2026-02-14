<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nit', 50)->unique();
            $table->string('nombre', 180);
            $table->string('direccion', 180)->nullable();
            $table->foreignId('ciudad_id')
                  ->constrained('ciudades')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('logo')->nullable(); // ruta archivo
            $table->timestamps();

            $table->index(['nombre', 'email']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
