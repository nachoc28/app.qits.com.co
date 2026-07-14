<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow;
use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepGeneration;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\AiFlows\AiFlowExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowStepResultCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_save_empty_result(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("resultTexts.{$executionStep->id}", '')
            ->call('saveResult', $executionStep->id)
            ->assertSee('no puede estar vacío');

        $this->assertSame(0, AiFlowStepResult::query()->count());
    }

    public function test_cannot_save_result_without_generation(): void
    {
        [$admin, $execution, $step] = $this->executionWithOneStep();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("resultTexts.{$executionStep->id}", 'Resultado GPT')
            ->call('saveResult', $executionStep->id)
            ->assertSee('Primero debes generar un prompt');
    }

    public function test_save_result_creates_row_associated_to_latest_generation(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);
        $latestGeneration = AiFlowStepGeneration::query()->where('ai_flow_execution_step_id', $executionStep->id)->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("resultTexts.{$executionStep->id}", 'Resultado GPT')
            ->call('saveResult', $executionStep->id)
            ->assertSee('Resultado guardado correctamente.');

        $this->assertDatabaseHas('ai_flow_step_results', [
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_generation_id' => $latestGeneration->id,
            'result_text' => 'Resultado GPT',
            'saved_by' => $admin->id,
        ]);
    }

    public function test_multiple_results_are_historical_and_latest_is_visible(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("resultTexts.{$executionStep->id}", 'Resultado uno')
            ->call('saveResult', $executionStep->id)
            ->set("resultTexts.{$executionStep->id}", 'Resultado dos')
            ->call('saveResult', $executionStep->id)
            ->assertSee('Ultimo resultado guardado')
            ->assertSee('Resultado dos')
            ->assertSee('Historial de resultados (2)');

        $this->assertSame(2, AiFlowStepResult::query()->where('ai_flow_execution_step_id', $executionStep->id)->count());
    }

    public function test_cannot_complete_step_without_result(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('completeStep', $executionStep->id)
            ->assertSee('guardar primero un resultado');

        $this->assertSame(AiFlowExecutionStep::STATUS_IN_PROGRESS, $executionStep->fresh()->status);
    }

    public function test_complete_step_updates_status_user_and_date(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);
        $this->createStepResultForTest($executionStep, $admin);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('completeStep', $executionStep->id)
            ->assertSee('Etapa completada correctamente.');

        $executionStep->refresh();
        $this->assertSame(AiFlowExecutionStep::STATUS_COMPLETED, $executionStep->status);
        $this->assertSame($admin->id, $executionStep->completed_by);
        $this->assertNotNull($executionStep->completed_at);
    }

    public function test_completing_step_unlocks_next_step_visually(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $firstStep = $this->createStep($version, ['position' => 1, 'base_prompt' => 'Paso uno']);
        $secondStep = $this->createStep($version, ['position' => 2, 'name' => 'Paso dos', 'base_prompt' => 'Paso dos']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecucion', $admin);
        $firstExecutionStep = $this->executionStep($execution, $firstStep);
        $this->createGeneration($firstExecutionStep, $admin);
        $this->createStepResultForTest($firstExecutionStep, $admin);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('completeStep', $firstExecutionStep->id)
            ->assertSee('Paso dos')
            ->assertSee('Pendiente');

        $secondExecutionStep = $this->executionStep($execution, $secondStep);
        $rows = app(AiFlowExecutionService::class)->stepProgressRows($execution->fresh(['steps.step']));
        $secondRow = collect($rows)->firstWhere('execution_step.id', $secondExecutionStep->id);
        $this->assertFalse($secondRow['is_blocked']);
    }

    public function test_execution_becomes_completed_when_all_steps_are_completed(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);
        $this->createStepResultForTest($executionStep, $admin);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('completeStep', $executionStep->id);

        $execution->refresh();
        $this->assertSame(AiFlowExecution::STATUS_COMPLETED, $execution->status);
        $this->assertNotNull($execution->completed_at);
    }

    public function test_output_variable_uses_latest_saved_source_result(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $sourceStep = $this->createStep($version, ['position' => 1, 'base_prompt' => 'Fuente']);
        $targetStep = $this->createStep($version, ['position' => 2, 'base_prompt' => 'Usa {{resultado_previo}}.']);
        $this->createVariable($version, [
            'name' => 'resultado_previo',
            'label' => 'Resultado previo',
            'scope' => AiFlowVariable::SCOPE_OUTPUT,
            'source_step_id' => $sourceStep->id,
        ]);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecucion', $admin);
        $sourceExecutionStep = $this->executionStep($execution, $sourceStep);
        $this->createGeneration($sourceExecutionStep, $admin);
        $this->createStepResultForTest($sourceExecutionStep, $admin, 'Resultado viejo', now()->subMinute());
        $this->createStepResultForTest($sourceExecutionStep, $admin, 'Resultado nuevo', now());
        $sourceExecutionStep->forceFill(['status' => AiFlowExecutionStep::STATUS_COMPLETED])->save();
        $targetExecutionStep = $this->executionStep($execution->fresh(['steps.step']), $targetStep);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $targetExecutionStep->id)
            ->assertSee('Prompt generado correctamente.');

        $this->assertSame('Usa Resultado nuevo.', AiFlowStepGeneration::query()->where('ai_flow_execution_step_id', $targetExecutionStep->id)->firstOrFail()->final_prompt_text);
    }

    public function test_output_variable_without_result_shows_clear_error(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $sourceStep = $this->createStep($version, ['position' => 1, 'base_prompt' => 'Fuente']);
        $targetStep = $this->createStep($version, ['position' => 2, 'base_prompt' => 'Usa {{resultado_previo}}.']);
        $this->createVariable($version, [
            'name' => 'resultado_previo',
            'label' => 'Resultado previo',
            'scope' => AiFlowVariable::SCOPE_OUTPUT,
            'source_step_id' => $sourceStep->id,
        ]);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecucion', $admin);
        $this->executionStep($execution, $sourceStep)->forceFill(['status' => AiFlowExecutionStep::STATUS_COMPLETED])->save();
        $targetExecutionStep = $this->executionStep($execution->fresh(['steps.step']), $targetStep);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $targetExecutionStep->id)
            ->assertSee('aún no tiene resultado disponible');
    }

    public function test_non_admin_cannot_save_results_or_complete_steps(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $user = $this->createUser('Cliente', $execution->empresa);
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($user)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertForbidden();

        $this->assertSame(AiFlowExecutionStep::STATUS_IN_PROGRESS, $executionStep->fresh()->status);
    }

    public function test_copy_prompt_button_appears_when_generation_exists(): void
    {
        [$admin, $execution] = $this->executionWithGeneratedPrompt();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Copiar prompt');
    }

    public function test_copy_prompt_button_does_not_appear_without_generation(): void
    {
        [$admin, $execution] = $this->executionWithOneStep();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertDontSee('Copiar prompt');
    }

    public function test_visible_prompt_is_latest_generation(): void
    {
        [$admin, $execution, $step] = $this->executionWithOneStep();
        $executionStep = $this->executionStep($execution, $step);
        $this->createGeneration($executionStep, $admin, 'Prompt anterior', now()->subMinute());
        $this->createGeneration($executionStep, $admin, 'Prompt mas reciente', now());

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Prompt mas reciente');
    }

    public function test_copy_prompt_feedback_does_not_create_database_records(): void
    {
        [$admin, $execution, $step] = $this->executionWithGeneratedPrompt();
        $executionStep = $this->executionStep($execution, $step);
        $generationCount = AiFlowStepGeneration::query()->count();
        $resultCount = AiFlowStepResult::query()->count();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('copyPromptFeedback', $executionStep->id)
            ->assertSee('Prompt copiado')
            ->assertSee('GPT recomendado');

        $this->assertSame($generationCount, AiFlowStepGeneration::query()->count());
        $this->assertSame($resultCount, AiFlowStepResult::query()->count());
    }

    private function executionWithGeneratedPrompt(): array
    {
        [$admin, $execution, $step] = $this->executionWithOneStep();
        $executionStep = $this->executionStep($execution, $step);
        $this->createGeneration($executionStep, $admin);
        $executionStep->forceFill(['status' => AiFlowExecutionStep::STATUS_IN_PROGRESS])->save();

        return [$admin, $execution, $step];
    }

    private function executionWithOneStep(): array
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version, ['position' => 1, 'base_prompt' => 'Prompt base']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecucion', $admin);

        return [$admin, $execution, $step];
    }

    private function createGeneration(AiFlowExecutionStep $executionStep, User $admin, string $prompt = 'Prompt generado', $generatedAt = null): AiFlowStepGeneration
    {
        return AiFlowStepGeneration::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_id' => $executionStep->ai_flow_step_id,
            'final_prompt_text' => $prompt,
            'variables_snapshot_json' => [],
            'generated_by' => $admin->id,
            'generated_at' => $generatedAt ?: now(),
        ]);
    }

    private function createStepResultForTest(AiFlowExecutionStep $executionStep, User $admin, string $text = 'Resultado GPT', $savedAt = null): AiFlowStepResult
    {
        $generation = AiFlowStepGeneration::query()
            ->where('ai_flow_execution_step_id', $executionStep->id)
            ->orderByDesc('id')
            ->first() ?: $this->createGeneration($executionStep, $admin);

        return AiFlowStepResult::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_generation_id' => $generation->id,
            'result_text' => $text,
            'saved_by' => $admin->id,
            'saved_at' => $savedAt ?: now(),
        ]);
    }

    private function executionStep($execution, AiFlowStep $step): AiFlowExecutionStep
    {
        return AiFlowExecutionStep::query()
            ->where('ai_flow_execution_id', $execution->id)
            ->where('ai_flow_step_id', $step->id)
            ->firstOrFail();
    }

    private function createPublishedFlow(User $admin): array
    {
        $flow = AiFlow::create([
            'key' => 'flujo_' . uniqid(),
            'name' => 'Flujo IA',
            'description' => 'Descripcion base',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $version = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);

        return [$flow, $version];
    }

    private function createStep(AiFlowVersion $version, array $attributes = []): AiFlowStep
    {
        return AiFlowStep::create(array_merge([
            'ai_flow_version_id' => $version->id,
            'step_key' => 'paso_' . uniqid(),
            'name' => 'Paso base',
            'description' => 'Etapa base',
            'position' => 1,
            'recommended_gpt' => '@GPT',
            'expected_output_name' => 'Salida',
            'base_prompt' => 'Prompt base',
            'is_active' => true,
        ], $attributes));
    }

    private function createVariable(AiFlowVersion $version, array $attributes = []): AiFlowVariable
    {
        return AiFlowVariable::create(array_merge([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => null,
            'source_step_id' => null,
            'name' => 'pais',
            'label' => 'Pais',
            'scope' => AiFlowVariable::SCOPE_GLOBAL,
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
            'is_required' => true,
            'help_text' => null,
            'placeholder' => null,
            'position' => 1,
            'default_value' => null,
        ], $attributes));
    }

    private function createEmpresa(string $name): Empresa
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        return Empresa::create([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => $name,
            'direccion' => 'Calle 123',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => strtolower(str_replace(' ', '', $name)) . uniqid() . '@test.local',
            'active' => true,
        ]);
    }

    private function createUser(string $roleName, ?Empresa $empresa = null): User
    {
        $tipoUsuario = TipoUsuario::query()->firstOrCreate([
            'nombre' => $roleName,
        ]);

        return User::create([
            'name' => 'Usuario Test ' . uniqid(),
            'email' => 'user' . uniqid() . '@test.local',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'empresa_id' => $empresa ? $empresa->id : null,
            'tipo_usuario_id' => $tipoUsuario->id,
            'active' => true,
        ]);
    }
}
