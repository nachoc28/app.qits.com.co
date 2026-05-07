<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_notification_public_links', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('whatsapp_form_notification_id');
            $table->foreign('whatsapp_form_notification_id', 'fnpl_wfn_id_foreign')
                  ->references('id')
                  ->on('whatsapp_form_notifications')
                  ->cascadeOnDelete();

            // Hash del token público (nunca guardar el token en claro)
            $table->string('token_hash', 64)->unique();  // SHA-256 hex del token generado en app

            $table->boolean('is_active')->default(true);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('first_accessed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);

            $table->timestamps();

            $table->index('token_hash', 'fnpl_token_hash_idx');
            $table->index(
                ['whatsapp_form_notification_id', 'is_active'],
                'fnpl_notification_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_notification_public_links');
    }
};
