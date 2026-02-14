<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->unique(['pais_id','nombre'], 'departamentos_pais_nombre_unique');
        });
        Schema::table('ciudades', function (Blueprint $table) {
            $table->unique(['departamento_id','nombre'], 'ciudades_depto_nombre_unique');
        });
    }
    public function down(): void {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropUnique('departamentos_pais_nombre_unique');
        });
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropUnique('ciudades_depto_nombre_unique');
        });
    }
};
