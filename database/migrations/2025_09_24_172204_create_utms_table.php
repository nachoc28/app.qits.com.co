<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('utm', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proyecto_empresa_id')
                ->constrained('proyectos_empresas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('fecha')->index();
            $table->string('event_type', 80)->index();
            $table->string('visitor_guid', 120)->nullable()->index();

            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();

            $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
            $table->string('landing_url', 2048)->nullable();
            $table->json('extra_data')->nullable();

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('utm');
    }
};
