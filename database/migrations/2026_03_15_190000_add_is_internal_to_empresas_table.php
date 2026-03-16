<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('active');
                $table->index('is_internal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'is_internal')) {
                $table->dropIndex(['is_internal']);
                $table->dropColumn('is_internal');
            }
        });
    }
};
