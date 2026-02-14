<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // agrega la columna deleted_at para soft deletes
            if (! Schema::hasColumn('empresas', 'deleted_at')) {
                $table->softDeletes(); // crea TIMESTAMP NULL 'deleted_at'
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
