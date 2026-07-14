<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowExecutionForm;
use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowExecutionStep;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\AiFlows\AiFlowExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowExecutionBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_execution_listing(): void
    {
        $admin = $this->createUser('Administrador');

        $this->actingAs($admin)
            ->get(route('admin.ai-flow-executions.index'))
            ->assertOk()
            ->assertSee('Ejecuciones de Flujos IA');
    }

    public function test_non_admin_cannot_view_execution_listing(): void
    {
        $user = $this->createUser('Cliente', $this->createEmpresa('Cliente Base'));

        $this->actingAs($user)
            ->get(route('admin.ai-flow-executions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_create_execution_screen(): void
    {
        $admin = $this->createUser('Administrador');

        $this->actingAs($admin)
            ->get(route('admin.ai-flow-executions.create'))
            ->assertOk()
            ->assertSee('Nueva ejecución de Flujo IA');
    }

    public function test_non_admin_cannot_create_execution(): void
    {
        $user = $this->createUser('Cliente', $this->createEmpresa('Cliente Base'));

        $this->actingAs($user)
            ->get(route('admin.ai-flow-executions.create'))
            ->assertForbidden();
    }

    public function test_only_active_flows_with_published_version_are_available_to_start(): void
    {
        $admin = $this->createUser('Administrador');
        [$publishedFlow] = $this->createPublishedFlow($admin, 'flujo_publicado', 'Flujo publicado');
        $this->createDraftFlow($admin, 'flujo_borrador', 'Flujo sin publicar');
        [$inactiveFlow] = $this->createPublishedFlow($admin, 'flujo_inactivo', 'Flujo inactivo');
        $inactiveFlow->forceFill(['is_active' => false])->save();

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionForm::class)
            ->assertSee('Flujo publicado')
            ->assertDontSee('Flujo sin publicar')
            ->assertDontSee('Flujo inactivo');

        $this->assertTrue($publishedFlow->fresh()->is_active);
    }

    public function test_cannot_start_execution_for_flow_without_published_version(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow] = $this->createDraftFlow($admin, 'flujo_borrador', 'Flujo sin publicar');

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionForm::class)
            ->set('empresa_id', (string) $empresa->id)
            ->set('ai_flow_id', (string) $flow->id)
            ->set('title', 'Ejecución inválida')
            ->call('createExecution')
            ->assertHasErrors(['ai_flow_id'])
            ->assertSee('Solo se pueden iniciar flujos activos con una versión publicada.');

        $this->assertDatabaseMissing('ai_flow_executions', [
            'title' => 'Ejecución inválida',
        ]);
    }

    public function test_create_execution_stores_main_fields_and_initializes_active_steps(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $firstStep = $this->createStep($version, ['position' => 1, 'name' => 'Paso uno']);
        $secondStep = $this->createStep($version, ['position' => 2, 'name' => 'Paso dos']);
        $this->createStep($version, ['position' => 3, 'name' => 'Paso inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(AiFlowExecutionForm::class)
            ->set('empresa_id', (string) $empresa->id)
            ->set('ai_flow_id', (string) $flow->id)
            ->set('title', 'Investigación empresa')
            ->call('createExecution')
            ->assertRedirect();

        $execution = AiFlowExecution::query()->where('title', 'Investigación empresa')->firstOrFail();

        $this->assertSame($empresa->id, $execution->empresa_id);
        $this->assertSame($flow->id, $execution->ai_flow_id);
        $this->assertSame($version->id, $execution->ai_flow_version_id);
        $this->assertSame(AiFlowExecution::STATUS_IN_PROGRESS, $execution->status);
        $this->assertSame($admin->id, $execution->started_by);
        $this->assertNotNull($execution->started_at);

        $this->assertDatabaseHas('ai_flow_execution_steps', [
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_step_id' => $firstStep->id,
            'status' => AiFlowExecutionStep::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('ai_flow_execution_steps', [
            'ai_flow_execution_id' => $execution->id,
            'ai_flow_step_id' => $secondStep->id,
            'status' => AiFlowExecutionStep::STATUS_PENDING,
        ]);
        $this->assertSame(2, $execution->steps()->count());
    }

    public function test_execution_is_frozen_to_published_version_used_at_creation(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $this->createStep($version, ['position' => 1]);

        app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución congelada', $admin);

        $newVersion = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 2,
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);

        $execution = AiFlowExecution::query()->where('title', 'Ejecución congelada')->firstOrFail();

        $this->assertSame($version->id, $execution->ai_flow_version_id);
        $this->assertNotSame($newVersion->id, $execution->ai_flow_version_id);
    }

    public function test_execution_detail_shows_steps_in_order(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $this->createStep($version, ['position' => 2, 'name' => 'Segunda etapa']);
        $this->createStep($version, ['position' => 1, 'name' => 'Primera etapa']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución ordenada', $admin);

        $this->actingAs($admin)
            ->get(route('admin.ai-flow-executions.show', $execution))
            ->assertOk()
            ->assertSeeInOrder(['Primera etapa', 'Segunda etapa']);
    }

    public function test_sequential_dependency_blocks_later_steps_when_no_explicit_dependencies_exist(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $this->createStep($version, ['position' => 1, 'name' => 'Paso uno']);
        $this->createStep($version, ['position' => 2, 'name' => 'Paso dos']);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución secuencial', $admin);

        $rows = app(AiFlowExecutionService::class)->stepProgressRows($execution);

        $this->assertSame('Pendiente', $rows[0]['visual_label']);
        $this->assertSame('Bloqueada', $rows[1]['visual_label']);
    }

    public function test_explicit_dependencies_block_and_unlock_independently_from_sequential_default(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        [$flow, $version] = $this->createPublishedFlow($admin);
        $firstStep = $this->createStep($version, ['position' => 1, 'name' => 'Paso uno']);
        $this->createStep($version, ['position' => 2, 'name' => 'Paso dos']);
        $thirdStep = $this->createStep($version, ['position' => 3, 'name' => 'Paso tres']);
        AiFlowStepDependency::create([
            'ai_flow_step_id' => $thirdStep->id,
            'depends_on_step_id' => $firstStep->id,
        ]);
        $execution = app(AiFlowExecutionService::class)->createExecution($empresa, $flow, 'Ejecución explícita', $admin);

        $rows = app(AiFlowExecutionService::class)->stepProgressRows($execution);
        $this->assertSame('Bloqueada', $rows[2]['visual_label']);

        AiFlowExecutionStep::query()
            ->where('ai_flow_execution_id', $execution->id)
            ->where('ai_flow_step_id', $firstStep->id)
            ->update(['status' => AiFlowExecutionStep::STATUS_COMPLETED]);

        $rows = app(AiFlowExecutionService::class)->stepProgressRows($execution->fresh(['steps.step']));
        $this->assertSame('Pendiente', $rows[2]['visual_label']);
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion}
     */
    private function createPublishedFlow(User $admin, string $key = 'flujo', string $name = 'Flujo publicado'): array
    {
        [$flow, $version] = $this->createDraftFlow($admin, $key, $name);
        $version->forceFill([
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $admin->id,
        ])->save();

        return [$flow, $version];
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion}
     */
    private function createDraftFlow(User $admin, string $key = 'flujo', string $name = 'Flujo IA'): array
    {
        $flow = AiFlow::create([
            'key' => $key . '_' . uniqid(),
            'name' => $name,
            'description' => 'Descripcion base',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $version = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
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
