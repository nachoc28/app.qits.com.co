<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_form_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();

            // Regla que originó la notificación (nullable: puede llegar antes de
            // que exista la regla, o ser creada manualmente en el futuro).
            $table->foreignId('form_forwarding_rule_id')
                  ->nullable()
                  ->constrained('form_forwarding_rules')
                  ->nullOnDelete();

            // Identificación del origen del envío
            $table->string('source_system', 80);        // p. ej. wordpress_cf7, gravity_forms
            $table->string('source_record_id', 191);    // ID del formulario en el sistema origen

            // Estados del ciclo de vida de la notificación
            $table->enum('status', [
                'pending',
                'queued',
                'awaiting_template',
                'skipped_no_recipient',
                'skipped_no_opt_in',
                'skipped_security',
                'sent',
                'delivered',
                'read',
                'failed',
                'expired',
            ])->default('pending');

            // Snapshot del destinatario en el momento del envío
            $table->string('destination_phone', 50)->nullable();

            // ID de mensaje devuelto por el proveedor WhatsApp (Cloud API, etc.)
            $table->string('whatsapp_message_id', 150)->nullable();

            // Payloads JSON
            $table->json('raw_payload_json');                       // payload crudo del formulario
            $table->json('normalized_payload_json')->nullable();    // payload normalizado / mapeado
            $table->json('message_payload_json')->nullable();       // cuerpo del mensaje a enviar
            $table->json('provider_response_json')->nullable();     // respuesta del proveedor

            // Detalle de fallo
            $table->text('failure_reason')->nullable();

            // Timestamps de ciclo de vida (opcionales hasta que ocurran)
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->timestamps();

            // Clave de idempotencia: un registro por envío único
            $table->unique(
                ['empresa_id', 'source_system', 'source_record_id'],
                'wfn_empresa_source_unique'
            );

            // Índices de consulta frecuente
            $table->index(['empresa_id', 'status'], 'wfn_empresa_status_idx');
            $table->index('status', 'wfn_status_idx');
            $table->index('whatsapp_message_id', 'wfn_wa_message_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_form_notifications');
    }
};
