<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa_seo_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->unique()
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->string('site_url', 500);
            $table->string('search_console_property', 255)->nullable();
            $table->string('ga4_property_id', 120)->nullable();
            $table->string('wordpress_site_url', 500)->nullable();
            $table->boolean('utm_tracking_enabled')->default(false);
            $table->boolean('gsc_enabled')->default(false);
            $table->boolean('ga4_enabled')->default(false);
            $table->timestamp('last_gsc_sync_at')->nullable();
            $table->timestamp('last_ga4_sync_at')->nullable();
            $table->timestamp('last_utm_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_gsc_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('avg_position', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'metric_date']);
            $table->index('metric_date');
        });

        Schema::create('seo_gsc_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('query', 191);
            $table->string('page_url', 500)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('avg_position', 8, 2)->default(0);
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('metric_date');
            $table->index('query');
            $table->index(['empresa_id', 'metric_date']);
        });

        Schema::create('seo_gsc_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('page_url', 255);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('avg_position', 8, 2)->default(0);
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('metric_date');
            $table->index('page_url');
            $table->index(['empresa_id', 'metric_date']);
        });

        Schema::create('seo_ga4_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('engaged_sessions')->nullable();
            $table->unsignedInteger('conversions')->nullable();
            $table->unsignedInteger('organic_sessions')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'metric_date']);
            $table->index('metric_date');
        });

        Schema::create('seo_ga4_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('landing_page', 255);
            $table->unsignedInteger('users')->nullable();
            $table->unsignedInteger('sessions')->nullable();
            $table->unsignedInteger('conversions')->nullable();
            $table->decimal('engagement_rate', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'metric_date']);
            $table->index('landing_page');
        });

        Schema::create('seo_utm_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->dateTime('conversion_datetime');
            $table->string('page_url', 500)->nullable();
            $table->string('form_name', 150)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('medium', 120)->nullable();
            $table->string('campaign', 150)->nullable();
            $table->string('term', 150)->nullable();
            $table->string('content', 150)->nullable();
            $table->string('event_name', 120)->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->longText('raw_payload_json')->nullable();
            $table->timestamps();

            $table->index('conversion_datetime');
            $table->index(['empresa_id', 'conversion_datetime']);
            $table->index(['empresa_id', 'source', 'medium', 'campaign']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_utm_conversions');
        Schema::dropIfExists('seo_ga4_landing_pages');
        Schema::dropIfExists('seo_ga4_daily_metrics');
        Schema::dropIfExists('seo_gsc_pages');
        Schema::dropIfExists('seo_gsc_queries');
        Schema::dropIfExists('seo_gsc_daily_metrics');
        Schema::dropIfExists('empresa_seo_properties');
    }
};
