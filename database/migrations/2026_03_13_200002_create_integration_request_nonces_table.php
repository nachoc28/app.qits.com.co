<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_request_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')
                  ->constrained('empresa_integrations')
                  ->cascadeOnDelete();

            // El nonce debe ser globalmente único en la tabla para garantizar replay protection
            $table->string('nonce', 100)->unique();

            // Hash de la firma del request completo (opcional, para verificación adicional)
            $table->string('request_signature_hash', 255)->nullable();

            // Ventana de validez del nonce (TTL corto, ej. 5 minutos)
            $table->timestamp('expires_at');

            $table->timestamps();

            // Índices para purgas programadas y lookups por integración
            $table->index('integration_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_request_nonces');
    }
};
