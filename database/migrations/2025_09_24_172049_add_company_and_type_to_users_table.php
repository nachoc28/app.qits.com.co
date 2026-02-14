<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // empresa (nullable)
            $table->foreignId('empresa_id')->nullable()
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // teléfono (opcional)
            $table->string('telefono', 50)->nullable()->after('password');

            // tipo de usuario (nullable para evitar fallos si ya hay datos; podrás forzarlo por app)
            $table->foreignId('tipo_usuario_id')->nullable()
                ->constrained('tipos_usuarios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // índices útiles
            $table->index(['empresa_id', 'tipo_usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->dropColumn('empresa_id');

            $table->dropColumn('telefono');

            $table->dropConstrainedForeignId('tipo_usuario_id');
            $table->dropColumn('tipo_usuario_id');
        });
    }
};
