<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresa_whatsapp_settings', function (Blueprint $table) {
            // Opt-in del número destinatario
            // nullable → compatible con registros existentes antes del MVP
            $table->boolean('destination_opt_in')
                  ->nullable()
                  ->default(null)
                  ->after('destination_phone')
                  ->comment('null=desconocido, true=opt-in confirmado, false=rechazado');

            $table->timestamp('destination_opt_in_at')
                  ->nullable()
                  ->after('destination_opt_in')
                  ->comment('Fecha en que se registró el opt-in/opt-out');

            $table->string('destination_opt_in_source', 80)
                  ->nullable()
                  ->after('destination_opt_in_at')
                  ->comment('Origen del consentimiento: manual, import, webhook, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn([
                'destination_opt_in',
                'destination_opt_in_at',
                'destination_opt_in_source',
            ]);
        });
    }
};
