<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            // source_id: nullable → si se borra la fuente el lead se conserva
            $table->foreignId('source_id')
                  ->nullable()
                  ->constrained('lead_sources')
                  ->nullOnDelete();
            $table->string('full_name', 180);
            $table->string('phone', 50);
            $table->string('email', 180)->nullable();
            $table->string('company', 180)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('origin_url', 500)->nullable();
            $table->string('origin_form_name', 150)->nullable();
            $table->text('message')->nullable();
            $table->longText('payload_json')->nullable();   // payload completo del formulario
            $table->string('status', 30)->default('received');
            $table->softDeletes();
            $table->timestamps();

            $table->index('phone');
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_leads');
    }
};
