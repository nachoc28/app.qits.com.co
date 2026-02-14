<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('codigo_divipola', 5)->nullable()->after('nombre');
            $table->unique('codigo_divipola'); // único por código DANE
        });
    }
    public function down(): void {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropUnique(['codigo_divipola']);
            $table->dropColumn('codigo_divipola');
        });
    }
};

