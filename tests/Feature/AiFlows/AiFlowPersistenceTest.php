<?php

namespace Tests\Feature\AiFlows;

use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowStepGeneration;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowStrategicOutput;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\AiFlows\AiFlowAccessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AiFlowPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_and_main_relationships_work(): void
    {
        $empresa = $this->createEmpresa('Empresa IA');
        $admin = $this->createUser('Administrador');
        [$flow, $version, $step] = $this->createPublishedFlow($admin);

        $variable = AiFlowVariable::create([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => null,
            'name' => 'mercado_objetivo',
            'label' => 'Mercado objetivo',
            'scope' => AiFlowVariable::SCOPE_STEP,
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'is_required' => true,
            'position' => 1,
        ]);

        $execution = AiFlowExecution::create([
            'empresa_id' => $empresa->id,
            'ai_flow_id' => $flow->id,
            'ai_flow_version_id' => $version->id,
            'title' => 'Investigacion de mercado',
            'status' => AiFlowExecution::STATUS_PENDING,
            'started_by' => $admin->id,
            'started_at' => now(),
        ]);

        $executionStep = AiFlowExecutionStep::create([
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_step_id' => $step->id,
            'status' => AiFlowExecutionStep::STATUS_PENDING,
        ]);

        $this->assertTrue($flow->versions->contains($version));
        $this->assertTrue($version->steps->contains($step));
        $this->assertTrue($version->variables->contains($variable));
        $this->assertTrue($execution->empresa->is($empresa));
        $this->assertTrue($execution->flow->is($flow));
        $this->assertTrue($execution->version->is($version));
        $this->assertTrue($executionStep->execution->is($execution));
        $this->assertTrue($executionStep->step->is($step));
    }

    public function test_generation_and_result_store_long_text(): void
    {
        $empresa = $this->createEmpresa('Empresa Texto Largo');
        $admin = $this->createUser('Administrador');
        [$flow, $version, $step] = $this->createPublishedFlow($admin);
        $execution = $this->createExecution($empresa, $admin, $flow, $version);
        $executionStep = $this->createExecutionStep($execution, $step);

        $longPrompt = str_repeat('Prompt largo para auditoria. ', 500);
        $longResult = str_repeat('Resultado externo del GPT. ', 600);

        $generation = AiFlowStepGeneration::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_id' => $step->id,
            'final_prompt_text' => $longPrompt,
            'variables_snapshot_json' => [
                'mercado_objetivo' => 'Pymes',
            ],
            'generated_by' => $admin->id,
            'generated_at' => now(),
        ]);

        $result = AiFlowStepResult::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_generation_id' => $generation->id,
            'result_text' => $longResult,
            'saved_by' => $admin->id,
            'saved_at' => now(),
        ]);

        $this->assertSame($longPrompt, $generation->fresh()->final_prompt_text);
        $this->assertSame(['mercado_objetivo' => 'Pymes'], $generation->fresh()->variables_snapshot_json);
        $this->assertSame($longResult, $result->fresh()->result_text);
        $this->assertTrue($result->generation->is($generation));
    }

    public function test_strategic_output_belongs_to_empresa_and_result(): void
    {
        $empresa = $this->createEmpresa('Empresa Estrategica');
        $admin = $this->createUser('Administrador');
        [$flow, $version, $step] = $this->createPublishedFlow($admin);
        $execution = $this->createExecution($empresa, $admin, $flow, $version);
        $executionStep = $this->createExecutionStep($execution, $step);
        $result = AiFlowStepResult::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_generation_id' => null,
            'result_text' => 'Base estrategica generada',
            'saved_by' => $admin->id,
            'saved_at' => now(),
        ]);

        $output = AiFlowStrategicOutput::create([
            'empresa_id' => $empresa->id,
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_result_id' => $result->id,
            'type' => AiFlowStrategicOutput::TYPE_CURRENT_STRATEGIC_BASE,
            'title' => 'Base vigente',
            'content' => 'Contenido estrategico reutilizable',
            'is_current' => true,
            'marked_by' => $admin->id,
            'marked_at' => now(),
        ]);

        $this->assertTrue($output->empresa->is($empresa));
        $this->assertTrue($output->execution->is($execution));
        $this->assertTrue($output->executionStep->is($executionStep));
        $this->assertTrue($output->stepResult->is($result));
        $this->assertTrue($empresa->aiFlowStrategicOutputs->contains($output));
    }

    public function test_variable_name_is_unique_per_version(): void
    {
        $admin = $this->createUser('Administrador');
        [, $version, $step] = $this->createPublishedFlow($admin);

        $this->createVariable($version, $step, 'cliente_objetivo');

        $this->expectException(QueryException::class);

        $this->createVariable($version, $step, 'cliente_objetivo');
    }

    public function test_variable_name_must_be_snake_case_without_accents_or_spaces(): void
    {
        $admin = $this->createUser('Administrador');
        [, $version, $step] = $this->createPublishedFlow($admin);

        $this->expectException(InvalidArgumentException::class);

        $this->createVariable($version, $step, 'cliente objetivo ágil');
    }

    public function test_step_dependency_must_belong_to_same_version(): void
    {
        $admin = $this->createUser('Administrador');
        [, , $firstStep] = $this->createPublishedFlow($admin, 'flujo_uno');
        [, , $secondStep] = $this->createPublishedFlow($admin, 'flujo_dos');

        $this->expectException(InvalidArgumentException::class);

        AiFlowStepDependency::create([
            'ai_flow_step_id' => $firstStep->id,
            'depends_on_step_id' => $secondStep->id,
        ]);
    }

    public function test_non_admin_cannot_access_ai_flows_through_access_service(): void
    {
        $empresa = $this->createEmpresa('Empresa Cliente');
        $user = $this->createUser('Cliente', $empresa);
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $execution = $this->createExecution($empresa, $admin, $flow, $version);
        $service = app(AiFlowAccessService::class);

        $this->assertFalse($service->canManageFlows($user));
        $this->assertFalse($service->canExecuteFlows($user));
        $this->assertFalse($service->canViewStrategicOutputs($user));
        $this->assertFalse($service->canAccessEmpresa($user, $empresa));
        $this->assertFalse($service->canAccessExecution($user, $execution));
        $this->assertCount(0, $service->authorizedEmpresas($user));
    }

    public function test_admin_can_access_ai_flows_through_access_service(): void
    {
        $empresa = $this->createEmpresa('Empresa Admin');
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $execution = $this->createExecution($empresa, $admin, $flow, $version);
        $service = app(AiFlowAccessService::class);

        $this->assertTrue($service->canManageFlows($admin));
        $this->assertTrue($service->canExecuteFlows($admin));
        $this->assertTrue($service->canViewStrategicOutputs($admin));
        $this->assertTrue($service->canAccessEmpresa($admin, $empresa));
        $this->assertTrue($service->canAccessExecution($admin, $execution));
        $this->assertTrue($service->authorizedEmpresas($admin)->contains('id', $empresa->id));
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion, 2: AiFlowStep}
     */
    private function createPublishedFlow(User $admin, string $key = 'investigacion_mercado'): array
    {
        $flow = AiFlow::create([
            'key' => $key,
            'name' => 'Investigacion de mercado ' . $key,
            'description' => 'Flujo base de pruebas',
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

        $step = AiFlowStep::create([
            'ai_flow_version_id' => $version->id,
            'step_key' => 'diagnostico',
            'name' => 'Diagnostico inicial',
            'description' => 'Primer paso',
            'position' => 1,
            'recommended_gpt' => '@InvestigadorMercado',
            'expected_output_name' => 'Diagnostico',
            'base_prompt' => 'Analiza {{cliente_objetivo}}.',
            'is_active' => true,
        ]);

        return [$flow, $version, $step];
    }

    private function createVariable(AiFlowVersion $version, AiFlowStep $step, string $name): AiFlowVariable
    {
        return AiFlowVariable::create([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => null,
            'name' => $name,
            'label' => 'Cliente objetivo',
            'scope' => AiFlowVariable::SCOPE_STEP,
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
            'is_required' => true,
            'position' => 1,
        ]);
    }

    private function createExecution(Empresa $empresa, User $admin, AiFlow $flow, AiFlowVersion $version): AiFlowExecution
    {
        return AiFlowExecution::create([
            'empresa_id' => $empresa->id,
            'ai_flow_id' => $flow->id,
            'ai_flow_version_id' => $version->id,
            'title' => 'Ejecucion ' . uniqid(),
            'status' => AiFlowExecution::STATUS_PENDING,
            'started_by' => $admin->id,
            'started_at' => now(),
        ]);
    }

    private function createExecutionStep(AiFlowExecution $execution, AiFlowStep $step): AiFlowExecutionStep
    {
        return AiFlowExecutionStep::create([
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_step_id' => $step->id,
            'status' => AiFlowExecutionStep::STATUS_PENDING,
        ]);
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
