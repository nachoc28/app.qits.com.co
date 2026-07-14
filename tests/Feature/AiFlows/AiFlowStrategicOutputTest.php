<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionShow;
use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepGeneration;
use App\Models\AiFlowStepResult;
use App\Models\AiFlowStrategicOutput;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\AiFlows\AiFlowExecutionService;
use App\Services\AiFlows\AiFlowStrategicOutputService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowStrategicOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_mark_strategic_output_if_step_is_not_completed(): void
    {
        [$admin, , $executionStep, $result] = $this->executionWithResult(AiFlowExecutionStep::STATUS_IN_PROGRESS);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('etapas completadas');

        app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT,
            'Informe',
            $admin
        );
    }

    public function test_cannot_mark_empty_result(): void
    {
        [$admin, , , $result] = $this->executionWithResult(AiFlowExecutionStep::STATUS_COMPLETED, '   ');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no puede estar');

        app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT,
            'Informe',
            $admin
        );
    }

    public function test_admin_can_mark_result_as_strategic_report(): void
    {
        [$admin, $execution, , $result] = $this->executionWithResult();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->set("strategicOutputTypes.{$result->id}", AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT)
            ->set("strategicOutputTitles.{$result->id}", 'Informe mercado')
            ->call('markStrategicOutput', $result->id)
            ->assertSee('Resultado');

        $this->assertDatabaseHas('ai_flow_strategic_outputs', [
            'ai_flow_step_result_id' => $result->id,
            'type' => AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT,
            'title' => 'Informe mercado',
            'is_current' => true,
            'marked_by' => $admin->id,
        ]);
    }

    public function test_admin_can_mark_result_as_executive_summary(): void
    {
        [$admin, , , $result] = $this->executionWithResult();

        $output = app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_EXECUTIVE_SUMMARY,
            'Resumen ejecutivo',
            $admin
        );

        $this->assertSame(AiFlowStrategicOutput::TYPE_EXECUTIVE_SUMMARY, $output->type);
        $this->assertTrue($output->is_current);
    }

    public function test_admin_can_mark_result_as_current_strategic_base(): void
    {
        [$admin, , , $result] = $this->executionWithResult();

        $output = app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_CURRENT_STRATEGIC_BASE,
            'Base vigente',
            $admin
        );

        $this->assertSame(AiFlowStrategicOutput::TYPE_CURRENT_STRATEGIC_BASE, $output->type);
        $this->assertTrue($output->is_current);
    }

    public function test_marking_new_output_for_same_empresa_and_type_disables_previous_current(): void
    {
        [$admin, $execution, , $firstResult] = $this->executionWithResult();
        $secondResult = $this->createStepResultForTest($this->executionStep($execution), $admin, 'Resultado dos');
        $service = app(AiFlowStrategicOutputService::class);

        $firstOutput = $service->markResult($firstResult, AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT, 'Informe uno', $admin);
        $secondOutput = $service->markResult($secondResult, AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT, 'Informe dos', $admin);

        $this->assertFalse($firstOutput->fresh()->is_current);
        $this->assertTrue($secondOutput->fresh()->is_current);
    }

    public function test_current_scope_is_empresa_and_type(): void
    {
        [$admin, , , $firstEmpresaResult] = $this->executionWithResult();
        [, , , $secondEmpresaResult] = $this->executionWithResult(AiFlowExecutionStep::STATUS_COMPLETED, 'Resultado otra empresa', 'Otra Empresa');
        $service = app(AiFlowStrategicOutputService::class);

        $firstOutput = $service->markResult($firstEmpresaResult, AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT, 'Informe empresa uno', $admin);
        $secondEmpresaOutput = $service->markResult($secondEmpresaResult, AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT, 'Informe empresa dos', $admin);

        $this->assertTrue($firstOutput->fresh()->is_current);
        $this->assertTrue($secondEmpresaOutput->fresh()->is_current);

        $summaryOutput = $service->markResult($firstEmpresaResult, AiFlowStrategicOutput::TYPE_EXECUTIVE_SUMMARY, 'Resumen empresa uno', $admin);

        $this->assertTrue($firstOutput->fresh()->is_current);
        $this->assertTrue($summaryOutput->fresh()->is_current);
    }

    public function test_listing_shows_strategic_outputs(): void
    {
        [$admin, , , $result] = $this->executionWithResult();
        app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_STRATEGIC_REPORT,
            'Informe visible',
            $admin
        );

        $this->actingAs($admin)
            ->get(route('admin.ai-flow-strategic-outputs.index'))
            ->assertOk()
            ->assertSee('Resultados')
            ->assertSee('Informe visible');
    }

    public function test_detail_shows_full_content(): void
    {
        [$admin, , , $result] = $this->executionWithResult(AiFlowExecutionStep::STATUS_COMPLETED, "Linea uno\nLinea dos");
        $output = app(AiFlowStrategicOutputService::class)->markResult(
            $result,
            AiFlowStrategicOutput::TYPE_CURRENT_STRATEGIC_BASE,
            'Base completa',
            $admin
        );

        $this->actingAs($admin)
            ->get(route('admin.ai-flow-strategic-outputs.show', $output))
            ->assertOk()
            ->assertSee('Base completa')
            ->assertSee('Linea uno')
            ->assertSee('Linea dos');
    }

    public function test_non_admin_cannot_access_or_mark_strategic_outputs(): void
    {
        [$admin, $execution, , $result] = $this->executionWithResult();
        $user = $this->createUser('Cliente', $execution->empresa);

        $this->actingAs($user)
            ->get(route('admin.ai-flow-strategic-outputs.index'))
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(AiFlowExecutionShow::class, ['executionId' => $execution->id])
            ->assertForbidden();

        $this->assertSame(0, AiFlowStrategicOutput::query()->count());
        $this->assertSame($admin->id, $result->saved_by);
    }

    private function executionWithResult(string $stepStatus = AiFlowExecutionStep::STATUS_COMPLETED, string $resultText = 'Resultado GPT', string $empresaName = 'Empresa Cliente'): array
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa($empresaName);
        [$flow, $version] = $this->createPublishedFlow($admin);
        $step = $this->createStep($version);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecucion', $admin);
        $executionStep = $this->executionStep($execution, $step);
        $executionStep->forceFill(['status' => $stepStatus])->save();
        $this->createGeneration($executionStep, $admin);
        $result = $this->createStepResultForTest($executionStep, $admin, $resultText);

        return [$admin, $execution, $executionStep, $result];
    }

    private function createGeneration(AiFlowExecutionStep $executionStep, User $admin): AiFlowStepGeneration
    {
        return AiFlowStepGeneration::create([
            'ai_flow_execution_step_id' => $executionStep->id,
            'ai_flow_step_id' => $executionStep->ai_flow_step_id,
            'final_prompt_text' => 'Prompt generado',
            'variables_snapshot_json' => [],
            'generated_by' => $admin->id,
            'generated_at' => now(),
        ]);
    }

    private function createStepResultForTest(AiFlowExecutionStep $executionStep, User $admin, string $text = 'Resultado GPT'): AiFlowStepResult
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
            'saved_at' => now(),
        ]);
    }

    private function executionStep(AiFlowExecution $execution, ?AiFlowStep $step = null): AiFlowExecutionStep
    {
        $query = AiFlowExecutionStep::query()->where('ai_flow_execution_id', $execution->id);

        if ($step) {
            $query->where('ai_flow_step_id', $step->id);
        }

        return $query->firstOrFail();
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

    private function createStep(AiFlowVersion $version): AiFlowStep
    {
        return AiFlowStep::create([
            'ai_flow_version_id' => $version->id,
            'step_key' => 'paso_' . uniqid(),
            'name' => 'Paso base',
            'description' => 'Etapa base',
            'position' => 1,
            'recommended_gpt' => '@GPT',
            'expected_output_name' => 'Salida',
            'base_prompt' => 'Prompt base',
            'is_active' => true,
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
