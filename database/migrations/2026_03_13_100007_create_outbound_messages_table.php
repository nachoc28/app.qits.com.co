<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            // lead_id: nullable → mensajes de prueba o sin lead vinculado
            $table->foreignId('lead_id')
                  ->nullable()
                  ->constrained('wa_leads')
                  ->nullOnDelete();
            $table->string('channel', 30)->default('whatsapp');
            $table->string('destination_phone', 50);
            $table->string('message_type', 50);             // text, template, document…
            $table->text('message_body')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('provider_message_id', 150)->nullable();
            $table->longText('provider_response')->nullable();
            $table->string('status', 30);                   // queued, sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
