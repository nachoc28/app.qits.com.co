<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('email', 180)->nullable();
            $table->string('telefono', 50)->nullable();

            $table->foreignId('tipo_proyecto_id')
                ->constrained('tipos_proyectos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('mensaje')->nullable();
            $table->string('origen', 60)->nullable(); // WhatsApp, Landing, Voz, etc.

            $table->enum('status', ['nuevo','en_contacto','descartado','convertido'])
                  ->default('nuevo');

            $table->foreignId('converted_user_id')->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'tipo_proyecto_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
