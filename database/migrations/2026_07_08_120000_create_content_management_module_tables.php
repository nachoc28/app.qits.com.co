<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->string('import_name', 180);
            $table->string('source_file_name', 255);
            $table->foreignId('imported_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['imported_by', 'imported_at']);
        });

        Schema::create('content_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_import_id')
                ->constrained('content_imports')
                ->cascadeOnDelete();
            $table->date('article_date');
            $table->string('topic', 255);
            $table->text('strategic_objective_general');
            $table->text('target_audience_general');
            $table->text('refined_objective')->nullable();
            $table->text('refined_target_audience')->nullable();
            $table->enum('tone', ['tuteo', 'usteo']);
            $table->enum('main_status', ['processing', 'unpublished', 'published'])
                ->default('processing');
            $table->enum('operational_stage', [
                'pending',
                'strategic_refinement',
                'drafting',
                'video_instagram',
                'final_file',
                'completed',
            ])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('published_url', 500)->nullable();
            $table->timestamps();

            $table->index(['main_status', 'operational_stage']);
        });

        Schema::create('content_article_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_article_id')
                ->constrained('content_articles')
                ->cascadeOnDelete();
            $table->enum('step_type', ['objective', 'drafting', 'video_instagram']);
            $table->enum('step_status', ['pending', 'in_progress', 'ready'])
                ->default('pending');
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['content_article_id', 'step_type'], 'content_article_step_unique');
        });

        Schema::create('content_master_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 180);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_master_template_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('content_master_template_id');
            $table->foreign('content_master_template_id', 'cmtv_template_fk')
                ->references('id')
                ->on('content_master_templates')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('template_body');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(
                ['content_master_template_id', 'version_number'],
                'content_template_version_unique'
            );
        });

        Schema::create('content_article_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_article_id')
                ->constrained('content_articles')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('content_master_template_version_id');
            $table->foreign('content_master_template_version_id', 'cag_template_version_fk')
                ->references('id')
                ->on('content_master_template_versions')
                ->restrictOnDelete();
            $table->enum('step_type', ['objective', 'drafting', 'video_instagram']);
            $table->longText('final_prompt_text');
            $table->foreignId('generated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['content_article_id', 'step_type']);
            $table->index(['generated_by', 'generated_at']);
        });

        Schema::create('content_article_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_article_id')
                ->constrained('content_articles')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['content_article_id', 'version_number'], 'content_article_file_version_unique');
            $table->index(['uploaded_by', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_article_files');
        Schema::dropIfExists('content_article_generations');
        Schema::dropIfExists('content_master_template_versions');
        Schema::dropIfExists('content_master_templates');
        Schema::dropIfExists('content_article_steps');
        Schema::dropIfExists('content_articles');
        Schema::dropIfExists('content_imports');
    }
};
