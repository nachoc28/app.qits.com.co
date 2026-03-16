<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();

            // Identificación
            $table->string('name', 150);
            $table->string('provider_type', 50)->default('generic'); // wordpress | generic | api | ...

            // Credenciales públicas / privadas
            $table->string('public_key', 100)->unique();             // identificador público (API key)
            $table->string('secret_hash', 255);                      // hash del secreto (bcrypt/argon), nunca en plano

            // Estado
            $table->string('status', 20)->default('active');         // active | suspended | revoked

            // Restricciones de acceso
            $table->text('allowed_domains_json')->nullable();        // JSON array de dominios/orígenes permitidos
            $table->text('allowed_ips_json')->nullable();            // JSON array de IPs o rangos CIDR permitidos

            // Permisos granulares
            $table->text('scopes_json');                             // JSON array de scopes otorgados

            // Límite de tasa
            $table->string('rate_limit_profile', 50)->nullable();    // default | strict | high

            // Auditoría de uso
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();          // IPv4 o IPv6

            // Extensibilidad
            $table->text('meta_json')->nullable();                   // metadatos extra sin esquema fijo

            $table->timestamps();

            // Índices adicionales (public_key ya tiene UNIQUE)
            $table->index('empresa_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_integrations');
    }
};
