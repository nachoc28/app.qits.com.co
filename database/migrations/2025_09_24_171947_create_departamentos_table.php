<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->foreignId('pais_id')
                  ->constrained('paises')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['pais_id', 'nombre']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
