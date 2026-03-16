<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa_whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->unique()                          // una empresa → un config
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->string('whatsapp_business_phone', 50);
            $table->string('whatsapp_phone_number_id', 100);
            $table->text('whatsapp_access_token');    // almacenar cifrado en modelo
            $table->string('whatsapp_verify_token', 100);
            $table->string('destination_phone', 50);
            $table->boolean('send_text_enabled')->default(true);
            $table->boolean('send_pdf_enabled')->default(true);
            $table->boolean('save_attachments')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_whatsapp_settings');
    }
};
