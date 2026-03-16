<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->cascadeOnDelete();
            $table->string('type', 50);                     // web_form, landing, referral…
            $table->string('domain', 255)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->string('campaign', 150)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('utm_source', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sources');
    }
};
