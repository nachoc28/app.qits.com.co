<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_security_logs', function (Blueprint $table) {
            $table->id();

            // FKs sin constrainted() intencionales: los logs deben sobrevivir al borrado
            // de una integración o empresa (trazabilidad de auditoría permanente).
            $table->unsignedBigInteger('integration_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();

            // Tipo de evento de seguridad
            $table->string('event_type', 80);       // auth_success | auth_failure | domain_blocked |
                                                     // ip_blocked | nonce_replayed | scope_denied | ...

            // Contexto de la solicitud
            $table->string('ip_address', 45)->nullable();   // IPv4 o IPv6
            $table->string('domain', 255)->nullable();
            $table->string('endpoint', 500);
            $table->string('http_method', 10);

            // Resultado
            $table->string('status', 20);           // allowed | denied | error
            $table->string('reason_code', 80)->nullable();  // código máquina (DOMAIN_NOT_ALLOWED, etc.)

            // Anti-replay / integridad
            $table->string('payload_fingerprint', 64)->nullable(); // SHA-256 del cuerpo del request

            // Metadatos opcionales (user agent, versión de cliente, etc.)
            $table->text('meta_json')->nullable();

            $table->timestamps();

            // Índices para búsquedas frecuentes y dashboards de seguridad
            $table->index('integration_id');
            $table->index('empresa_id');
            $table->index('event_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_security_logs');
    }
};
