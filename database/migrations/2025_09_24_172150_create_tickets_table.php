<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('resumen');
            $table->foreignId('responsable_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedInteger('tiempo_respuesta')->nullable(); // minutos estimados
            $table->enum('status', ['pendiente','enviado','cerrado'])
                  ->default('pendiente');

            $table->timestamps();

            $table->index(['responsable_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

