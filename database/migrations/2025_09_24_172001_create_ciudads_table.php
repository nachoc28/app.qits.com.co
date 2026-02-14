<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ciudades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->foreignId('departamento_id')
                  ->constrained('departamentos')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['departamento_id', 'nombre']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};
