<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                  ->constrained('wa_leads')
                  ->cascadeOnDelete();
            $table->string('event_type', 80);   // received, forwarded, pdf_generated, failed…
            $table->longText('payload_json')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_events');
    }
};
