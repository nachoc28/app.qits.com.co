<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->foreignId('servicio_id')
                  ->constrained('servicios')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'servicio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_servicio');
    }
};
