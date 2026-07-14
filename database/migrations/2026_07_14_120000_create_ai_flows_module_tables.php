<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_flows', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_id')
                ->constrained('ai_flows')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'published', 'archived'])
                ->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['ai_flow_id', 'version_number'], 'ai_flow_version_unique');
            $table->index(['ai_flow_id', 'status']);
        });

        Schema::create('ai_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_version_id')
                ->constrained('ai_flow_versions')
                ->cascadeOnDelete();
            $table->string('step_key', 120);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->string('recommended_gpt', 180)->nullable();
            $table->string('expected_output_name', 180)->nullable();
            $table->longText('base_prompt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ai_flow_version_id', 'step_key'], 'ai_flow_step_key_unique');
            $table->unique(['ai_flow_version_id', 'position'], 'ai_flow_step_position_unique');
        });

        Schema::create('ai_flow_step_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_step_id')
                ->constrained('ai_flow_steps')
                ->cascadeOnDelete();
            $table->foreignId('depends_on_step_id')
                ->constrained('ai_flow_steps')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['ai_flow_step_id', 'depends_on_step_id'],
                'ai_flow_step_dependency_unique'
            );
        });

        Schema::create('ai_flow_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_version_id')
                ->constrained('ai_flow_versions')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_step_id')
                ->nullable()
                ->constrained('ai_flow_steps')
                ->cascadeOnDelete();
            $table->foreignId('source_step_id')
                ->nullable()
                ->constrained('ai_flow_steps')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('label', 180);
            $table->enum('scope', ['global', 'step', 'output']);
            $table->enum('input_type', ['input', 'textarea'])
                ->default('input');
            $table->boolean('is_required')->default(true);
            $table->text('help_text')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->longText('default_value')->nullable();
            $table->timestamps();

            $table->unique(['ai_flow_version_id', 'name'], 'ai_flow_variable_name_unique');
            $table->index(['ai_flow_version_id', 'scope']);
            $table->index(['ai_flow_step_id', 'position']);
        });

        Schema::create('ai_flow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_id')
                ->constrained('ai_flows')
                ->restrictOnDelete();
            $table->foreignId('ai_flow_version_id')
                ->constrained('ai_flow_versions')
                ->restrictOnDelete();
            $table->string('title', 255);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                ->default('pending');
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['ai_flow_id', 'ai_flow_version_id']);
        });

        Schema::create('ai_flow_execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_execution_id')
                ->constrained('ai_flow_executions')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_step_id')
                ->constrained('ai_flow_steps')
                ->restrictOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed'])
                ->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['ai_flow_execution_id', 'ai_flow_step_id'],
                'ai_flow_execution_step_unique'
            );
            $table->index(['ai_flow_execution_id', 'status']);
        });

        Schema::create('ai_flow_execution_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_execution_id')
                ->constrained('ai_flow_executions')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_variable_id')
                ->constrained('ai_flow_variables')
                ->restrictOnDelete();
            $table->foreignId('ai_flow_execution_step_id')
                ->nullable()
                ->constrained('ai_flow_execution_steps')
                ->cascadeOnDelete();
            $table->longText('value')->nullable();
            $table->foreignId('filled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index(['ai_flow_execution_id', 'ai_flow_variable_id'], 'ai_flow_exec_value_var_idx');
            $table->index(['ai_flow_execution_step_id']);
        });

        Schema::create('ai_flow_step_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_execution_step_id')
                ->constrained('ai_flow_execution_steps')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_step_id')
                ->constrained('ai_flow_steps')
                ->restrictOnDelete();
            $table->longText('final_prompt_text');
            $table->json('variables_snapshot_json')->nullable();
            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['ai_flow_execution_step_id', 'generated_at'], 'ai_flow_generation_step_time_idx');
        });

        Schema::create('ai_flow_step_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_flow_execution_step_id')
                ->constrained('ai_flow_execution_steps')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_step_generation_id')
                ->nullable()
                ->constrained('ai_flow_step_generations')
                ->nullOnDelete();
            $table->longText('result_text');
            $table->foreignId('saved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->index(['ai_flow_execution_step_id', 'saved_at'], 'ai_flow_result_step_time_idx');
        });

        Schema::create('ai_flow_strategic_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')
                ->constrained('empresas')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_execution_id')
                ->constrained('ai_flow_executions')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_execution_step_id')
                ->constrained('ai_flow_execution_steps')
                ->cascadeOnDelete();
            $table->foreignId('ai_flow_step_result_id')
                ->constrained('ai_flow_step_results')
                ->cascadeOnDelete();
            $table->enum('type', [
                'strategic_report',
                'executive_summary',
                'current_strategic_base',
            ]);
            $table->string('title', 255);
            $table->longText('content');
            $table->boolean('is_current')->default(false);
            $table->foreignId('marked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'type', 'is_current'], 'ai_flow_output_current_idx');
            $table->index(['ai_flow_execution_id', 'type'], 'ai_flow_output_execution_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_flow_strategic_outputs');
        Schema::dropIfExists('ai_flow_step_results');
        Schema::dropIfExists('ai_flow_step_generations');
        Schema::dropIfExists('ai_flow_execution_values');
        Schema::dropIfExists('ai_flow_execution_steps');
        Schema::dropIfExists('ai_flow_executions');
        Schema::dropIfExists('ai_flow_variables');
        Schema::dropIfExists('ai_flow_step_dependencies');
        Schema::dropIfExists('ai_flow_steps');
        Schema::dropIfExists('ai_flow_versions');
        Schema::dropIfExists('ai_flows');
    }
};
