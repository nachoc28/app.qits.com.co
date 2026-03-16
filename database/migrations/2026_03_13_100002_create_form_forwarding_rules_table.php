<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_forwarding_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->string('site_key', 80)->unique();   // clave de API pública
            $table->string('form_name', 150)->nullable();
            $table->string('allowed_domain', 255);
            $table->string('allowed_origin_url', 500)->nullable();
            $table->text('message_template')->nullable();
            $table->boolean('generate_pdf_always')->default(true);
            $table->boolean('only_for_long_forms')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_forwarding_rules');
    }
};
