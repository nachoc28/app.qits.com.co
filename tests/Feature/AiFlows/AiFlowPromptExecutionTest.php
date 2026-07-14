<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow;
use App\Models\AiFlow;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowExecutionValue;
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

class AiFlowPromptExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_step_shows_dynamic_form(): void
    {
        [$admin, $execution, $step, $variable] = $this->executionWithVariable();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Variables de esta etapa')
            ->assertSee($variable->label)
            ->assertSee('Guardar variables')
            ->assertSee('Generar prompt');

        $this->assertSame(AiFlowExecutionStep::STATUS_PENDING, $executionStep->fresh()->status);
    }

    public function test_blocked_step_does_not_show_editable_form(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $this->createStep($version, ['position' => 1, 'name' => 'Paso uno', 'base_prompt' => 'Uno']);
        $secondStep = $this->createStep($version, ['position' => 2, 'name' => 'Paso dos', 'base_prompt' => 'Dos {{pais}}']);
        $this->createVariable($version, ['name' => 'pais', 'label' => 'País']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Esta etapa está bloqueada hasta completar las etapas requeridas.')
            ->assertSee('Paso dos');

        $secondExecutionStep = $this->executionStep($execution, $secondStep);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $secondExecutionStep->id)
            ->assertSee('Esta etapa está bloqueada hasta completar las etapas requeridas.');
    }

    public function test_input_and_textarea_variables_render_with_help_placeholder_and_default(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version, [
            'base_prompt' => "Analiza {{pais}}.\n{{objetivo}}",
        ]);
        $input = $this->createVariable($version, [
            'name' => 'pais',
            'label' => 'País',
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
            'help_text' => 'Indica el país objetivo.',
            'placeholder' => 'Colombia',
            'default_value' => 'Colombia default',
            'position' => 1,
        ]);
        $textarea = $this->createVariable($version, [
            'name' => 'objetivo',
            'label' => 'Objetivo',
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'help_text' => 'Describe el objetivo.',
            'placeholder' => 'Objetivo detallado',
            'default_value' => "Linea 1\nLinea 2",
            'position' => 2,
        ]);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Indica el país objetivo.')
            ->assertSee('Colombia')
            ->assertSee('Describe el objetivo.')
            ->assertSee('Objetivo detallado')
            ->assertSet("variableValues.{$executionStep->id}.{$input->id}", 'Colombia default')
            ->assertSet("variableValues.{$executionStep->id}.{$textarea->id}", "Linea 1\nLinea 2");
    }

    public function test_save_variables_creates_and_updates_values(): void
    {
        [$admin, $execution, $step, $variable] = $this->executionWithVariable();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("variableValues.{$executionStep->id}.{$variable->id}", 'Colombia')
            ->call('saveVariables', $executionStep->id)
            ->assertSee('Variables guardadas correctamente.')
            ->set("variableValues.{$executionStep->id}.{$variable->id}", 'México')
            ->call('saveVariables', $executionStep->id)
            ->assertSee('Variables guardadas correctamente.');

        $this->assertSame(1, AiFlowExecutionValue::query()->where('ai_flow_execution_id', $execution->id)->count());
        $this->assertDatabaseHas('ai_flow_execution_values', [
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_variable_id' => $variable->id,
            'ai_flow_execution_step_id' => null,
            'value' => 'México',
            'filled_by' => $admin->id,
        ]);
    }

    public function test_does_not_generate_prompt_when_required_variable_is_missing(): void
    {
        [$admin, $execution, $step, $variable] = $this->executionWithVariable();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("variableValues.{$executionStep->id}.{$variable->id}", '')
            ->call('saveVariables', $executionStep->id)
            ->call('generatePrompt', $executionStep->id)
            ->assertSee('no tiene valor');

        $this->assertSame(0, AiFlowStepGeneration::query()->count());
    }

    public function test_generates_prompt_replacing_global_and_step_variables_preserving_line_breaks_and_snapshot(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version, [
            'base_prompt' => "País: {{pais}}\nObjetivo:\n{{objetivo}}",
        ]);
        $global = $this->createVariable($version, ['name' => 'pais', 'label' => 'País']);
        $stepVariable = $this->createVariable($version, [
            'name' => 'objetivo',
            'label' => 'Objetivo',
            'scope' => AiFlowVariable::SCOPE_STEP,
            'ai_flow_step_id' => $step->id,
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'position' => 2,
        ]);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("variableValues.{$executionStep->id}.{$global->id}", 'Colombia')
            ->set("variableValues.{$executionStep->id}.{$stepVariable->id}", "Crecer leads\nMejorar conversión")
            ->call('saveVariables', $executionStep->id)
            ->call('generatePrompt', $executionStep->id)
            ->assertSee('Prompt generado correctamente.');

        $generation = AiFlowStepGeneration::query()->firstOrFail();
        $this->assertSame("País: Colombia\nObjetivo:\nCrecer leads\nMejorar conversión", $generation->final_prompt_text);
        $this->assertSame(AiFlowExecutionStep::STATUS_IN_PROGRESS, $executionStep->fresh()->status);
        $this->assertCount(2, $generation->variables_snapshot_json);
        $this->assertSame('pais', $generation->variables_snapshot_json[0]['variable']);
        $this->assertSame('objetivo', $generation->variables_snapshot_json[1]['variable']);
    }

    public function test_regenerating_prompt_creates_generation_history(): void
    {
        [$admin, $execution, $step, $variable] = $this->executionWithVariable();
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("variableValues.{$executionStep->id}.{$variable->id}", 'Colombia')
            ->call('saveVariables', $executionStep->id)
            ->call('generatePrompt', $executionStep->id)
            ->set("variableValues.{$executionStep->id}.{$variable->id}", 'México')
            ->call('saveVariables', $executionStep->id)
            ->call('generatePrompt', $executionStep->id)
            ->assertSee('Historial de generaciones (2)');

        $this->assertSame(2, AiFlowStepGeneration::query()->where('ai_flow_execution_step_id', $executionStep->id)->count());
    }

    public function test_prompt_with_unconfigured_variable_fails(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $executionStep->id)
            ->assertSee('no está configurada');
    }

    public function test_output_variable_without_result_fails_clearly(): void
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
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);
        $sourceExecutionStep = $this->executionStep($execution, $sourceStep);
        $sourceExecutionStep->forceFill(['status' => AiFlowExecutionStep::STATUS_COMPLETED])->save();
        $targetExecutionStep = $this->executionStep($execution->fresh(['steps.step']), $targetStep);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $targetExecutionStep->id)
            ->assertSee('aún no tiene resultado disponible');
    }

    public function test_output_variable_uses_latest_source_result_when_available(): void
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
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);
        $sourceExecutionStep = $this->executionStep($execution, $sourceStep);
        $sourceExecutionStep->forceFill(['status' => AiFlowExecutionStep::STATUS_COMPLETED])->save();
        AiFlowStepResult::create([
            'ai_flow_execution_step_id' => $sourceExecutionStep->id,
            'ai_flow_step_generation_id' => null,
            'result_text' => 'Resultado curado',
            'saved_by' => $admin->id,
            'saved_at' => now(),
        ]);
        $targetExecutionStep = $this->executionStep($execution->fresh(['steps.step']), $targetStep);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->call('generatePrompt', $targetExecutionStep->id)
            ->assertSee('Prompt generado correctamente.');

        $this->assertSame('Usa Resultado curado.', AiFlowStepGeneration::query()->firstOrFail()->final_prompt_text);
    }

    public function test_non_admin_cannot_execute_prompt_actions(): void
    {
        [$admin, $execution, $step] = $this->executionWithVariable();
        $user = $this->createUser('Cliente', $execution->empresa);
        $executionStep = $this->executionStep($execution, $step);

        Livewire::actingAs($user)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: \App\Models\AiFlowExecution, 2: AiFlowStep, 3: AiFlowVariable}
     */
    private function executionWithVariable(): array
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $variable = $this->createVariable($version, ['name' => 'pais', 'label' => 'País']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución', $admin);

        return [$admin, $execution, $step, $variable];
    }

    private function executionStep($execution, AiFlowStep $step): AiFlowExecutionStep
    {
        return AiFlowExecutionStep::query()
            ->where('ai_flow_execution_id', $execution->id)
            ->where('ai_flow_step_id', $step->id)
            ->firstOrFail();
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion}
     */
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

    /**
     * @param  array<string, mixed>  $attributes
     */
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
            'base_prompt' => 'Analiza {{pais}}.',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createVariable(AiFlowVersion $version, array $attributes = []): AiFlowVariable
    {
        return AiFlowVariable::create(array_merge([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => null,
            'source_step_id' => null,
            'name' => 'pais',
            'label' => 'País',
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
