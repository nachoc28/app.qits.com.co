<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seo_utm_conversions', function (Blueprint $table) {
            $table->string('source_system', 32)
                ->nullable()
                ->after('lead_id');

            $table->string('source_record_id', 191)
                ->nullable()
                ->after('source_system');

            $table->unique(
                ['empresa_id', 'source_system', 'source_record_id'],
                'seo_utm_conv_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('seo_utm_conversions', function (Blueprint $table) {
            $table->dropUnique('seo_utm_conv_source_unique');
            $table->dropColumn(['source_system', 'source_record_id']);
        });
    }
};
