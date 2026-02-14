<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipos_usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique(); // Administrador, Cliente, API User
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tipos_usuarios');
    }
};
