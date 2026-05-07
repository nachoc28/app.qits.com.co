<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_form_notifications', function (Blueprint $table) {
            $table->unsignedInteger('attempts')
                  ->default(0)
                  ->after('failure_reason');

            $table->timestamp('last_attempt_at')
                  ->nullable()
                  ->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_form_notifications', function (Blueprint $table) {
            $table->dropColumn([
                'attempts',
                'last_attempt_at',
            ]);
        });
    }
};
