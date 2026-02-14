<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('paises', function (Blueprint $table) {
            $table->char('iso2', 2)->nullable()->after('nombre');
            $table->unique('iso2'); // único por código
        });
    }
    public function down(): void {
        Schema::table('paises', function (Blueprint $table) {
            $table->dropUnique(['iso2']);
            $table->dropColumn('iso2');
        });
    }
};
