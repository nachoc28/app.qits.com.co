<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionForm;
use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow;
use App\Http\Livewire\Admin\AiFlows\AiFlowForm;
use App\Http\Livewire\Admin\AiFlows\AiFlowVersionIndex;
use App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow;
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

class AiFlowManualLoadingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manually_load_publish_and_execute_three_step_market_research_flow(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Oftalmologia Salgado');

        Livewire::actingAs($admin)
            ->test(AiFlowForm::class)
            ->set('name', 'Investigacion de Mercado Digital - Prueba')
            ->set('key', 'investigacion_mercado_digital_prueba')
            ->set('description', 'Flujo de prueba cargado manualmente')
            ->set('is_active', true)
            ->call('save')
            ->assertSee('Flujo IA creado correctamente.');

        $flow = AiFlow::query()->where('key', 'investigacion_mercado_digital_prueba')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AiFlowVersionIndex::class, ['flowId' => $flow->id])
            ->call('createDraftVersion')
            ->assertSee('Version');

        $version = AiFlowVersion::query()->where('ai_flow_id', $flow->id)->firstOrFail();

        $this->createStepFromUi($admin, $flow, $version, [
            'step_key' => 'validador_brief',
            'name' => 'Validador de brief',
            'position' => 1,
            'expected_output_name' => 'brief_validado.md',
            'base_prompt' => "Valida el brief para {{pais}}, {{ciudad}}.\nSector: {{industria_sector}}.\nObjetivo: {{objetivo_estrategico}}.",
        ]);
        $step0 = AiFlowStep::query()->where('ai_flow_version_id', $version->id)->where('step_key', 'validador_brief')->firstOrFail();

        $this->createStepFromUi($admin, $flow, $version, [
            'step_key' => 'investigador_fuentes',
            'name' => 'Investigador de fuentes',
            'position' => 2,
            'expected_output_name' => 'matriz_fuentes.md',
            'base_prompt' => "Investiga fuentes usando este brief validado:\n{{brief_validado_md}}",
            'depends_on_step_id' => (string) $step0->id,
        ]);
        $step1 = AiFlowStep::query()->where('ai_flow_version_id', $version->id)->where('step_key', 'investigador_fuentes')->firstOrFail();

        $this->createStepFromUi($admin, $flow, $version, [
            'step_key' => 'analisis_mercado',
            'name' => 'Analisis de mercado',
            'position' => 3,
            'expected_output_name' => 'analisis_mercado.md',
            'base_prompt' => "Analiza el mercado con:\nBrief:\n{{brief_validado_md}}\nFuentes:\n{{matriz_fuentes_md}}",
            'depends_on_step_id' => (string) $step1->id,
        ]);
        $step2 = AiFlowStep::query()->where('ai_flow_version_id', $version->id)->where('step_key', 'analisis_mercado')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->assertSee('Variables detectadas')
            ->assertSee('Tokens')
            ->call('syncVariables')
            ->assertSee('Variables nuevas creadas: 6');

        $this->assertDatabaseHas('ai_flow_variables', [
            'ai_flow_version_id' => $version->id,
            'name' => 'brief_validado_md',
        ]);

        $briefVariable = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'brief_validado_md')->firstOrFail();
        $fuentesVariable = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'matriz_fuentes_md')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $briefVariable->id)
            ->set('variable_scope', AiFlowVariable::SCOPE_OUTPUT)
            ->set('variable_source_step_id', (string) $step0->id)
            ->call('saveVariable')
            ->assertSee('Variable actualizada correctamente.')
            ->call('editVariable', $fuentesVariable->id)
            ->set('variable_scope', AiFlowVariable::SCOPE_OUTPUT)
            ->set('variable_source_step_id', (string) $step1->id)
            ->call('saveVariable')
            ->assertSee('Variable actualizada correctamente.')
            ->call('publish');

        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $version->fresh()->status);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionForm::class)
            ->set('empresa_id', (string) $empresa->id)
            ->set('ai_flow_id', (string) $flow->id)
            ->set('title', 'Prueba manual investigacion')
            ->call('createExecution')
            ->assertRedirect();

        $execution = AiFlowExecution::query()->where('title', 'Prueba manual investigacion')->firstOrFail();
        $executionStep0 = $this->executionStep($execution, $step0);
        $executionStep1 = $this->executionStep($execution, $step1);
        $executionStep2 = $this->executionStep($execution, $step2);

        $initialRows = app(AiFlowExecutionService::class)->stepProgressRows($execution->fresh(['steps.step']));
        $this->assertFalse($this->rowForExecutionStep($initialRows, $executionStep0)['is_blocked']);
        $this->assertTrue($this->rowForExecutionStep($initialRows, $executionStep1)['is_blocked']);
        $this->assertTrue($this->rowForExecutionStep($initialRows, $executionStep2)['is_blocked']);

        $pais = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'pais')->firstOrFail();
        $ciudad = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'ciudad')->firstOrFail();
        $industria = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'industria_sector')->firstOrFail();
        $objetivo = AiFlowVariable::query()->where('ai_flow_version_id', $version->id)->where('name', 'objetivo_estrategico')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertSee('Validador de brief')
            ->assertSee('Bloqueada')
            ->set("variableValues.{$executionStep0->id}.{$pais->id}", 'Colombia')
            ->set("variableValues.{$executionStep0->id}.{$ciudad->id}", 'Bogota')
            ->set("variableValues.{$executionStep0->id}.{$industria->id}", 'Salud visual')
            ->set("variableValues.{$executionStep0->id}.{$objetivo->id}", 'Encontrar oportunidades de crecimiento digital')
            ->call('saveVariables', $executionStep0->id)
            ->assertSee('Variables guardadas correctamente.')
            ->call('generatePrompt', $executionStep0->id)
            ->assertSee('Prompt generado correctamente.')
            ->set("resultTexts.{$executionStep0->id}", 'Brief validado para mercado de salud visual en Bogota.')
            ->call('saveResult', $executionStep0->id)
            ->assertSee('Resultado guardado correctamente.')
            ->call('completeStep', $executionStep0->id)
            ->assertSee('Etapa completada correctamente.')
            ->call('generatePrompt', $executionStep1->id)
            ->assertSee('Prompt generado correctamente.');

        $this->assertStringContainsString(
            'Brief validado para mercado de salud visual en Bogota.',
            AiFlowStepGeneration::query()
                ->where('ai_flow_execution_step_id', $executionStep1->id)
                ->latest('id')
                ->firstOrFail()
                ->final_prompt_text
        );

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("resultTexts.{$executionStep1->id}", 'Matriz de fuentes con competidores y tendencias locales.')
            ->call('saveResult', $executionStep1->id)
            ->assertSee('Resultado guardado correctamente.')
            ->call('completeStep', $executionStep1->id)
            ->assertSee('Etapa completada correctamente.')
            ->call('generatePrompt', $executionStep2->id)
            ->assertSee('Prompt generado correctamente.')
            ->set("resultTexts.{$executionStep2->id}", 'Analisis de mercado final.')
            ->call('saveResult', $executionStep2->id)
            ->call('completeStep', $executionStep2->id)
            ->assertSee('Etapa completada correctamente.');

        $finalPrompt = AiFlowStepGeneration::query()
            ->where('ai_flow_execution_step_id', $executionStep2->id)
            ->latest('id')
            ->firstOrFail()
            ->final_prompt_text;

        $this->assertStringContainsString('Brief validado para mercado de salud visual en Bogota.', $finalPrompt);
        $this->assertStringContainsString('Matriz de fuentes con competidores y tendencias locales.', $finalPrompt);
        $this->assertSame(AiFlowExecution::STATUS_COMPLETED, $execution->fresh()->status);
        $this->assertSame(3, AiFlowStepResult::query()->count());
    }

    private function createStepFromUi(User $admin, AiFlow $flow, AiFlowVersion $version, array $data): void
    {
        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', $data['step_key'])
            ->set('name', $data['name'])
            ->set('description', $data['description'] ?? '')
            ->set('position', $data['position'])
            ->set('recommended_gpt', $data['recommended_gpt'] ?? '@GPTInvestigacion')
            ->set('expected_output_name', $data['expected_output_name'])
            ->set('base_prompt', $data['base_prompt'])
            ->set('is_active', true)
            ->set('depends_on_step_id', $data['depends_on_step_id'] ?? '')
            ->call('saveStep')
            ->assertSee('Etapa');
    }

    private function executionStep(AiFlowExecution $execution, AiFlowStep $step): AiFlowExecutionStep
    {
        return AiFlowExecutionStep::query()
            ->where('ai_flow_execution_id', $execution->id)
            ->where('ai_flow_step_id', $step->id)
            ->firstOrFail();
    }

    private function rowForExecutionStep(array $rows, AiFlowExecutionStep $executionStep): array
    {
        return collect($rows)->first(static function (array $row) use ($executionStep): bool {
            return (int) $row['execution_step']->id === (int) $executionStep->id;
        });
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
