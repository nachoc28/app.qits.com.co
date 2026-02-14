<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proyectos_empresas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('url', 255);
            $table->foreignId('tipo_proyecto_id')
                ->constrained('tipos_proyectos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'url']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('proyectos_empresas');
    }
};
