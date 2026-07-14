<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow;
use App\Models\AiFlow;
use App\Models\AiFlowStep;
use App\Models\AiFlowStepDependency;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowStepBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_step_in_draft_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', 'diagnostico_inicial')
            ->set('name', 'Diagnóstico inicial')
            ->set('position', 1)
            ->set('recommended_gpt', '@InvestigadorMercado')
            ->set('expected_output_name', 'Diagnóstico')
            ->set('base_prompt', 'Analiza {{pais}}.')
            ->call('saveStep')
            ->assertSee('Etapa creada correctamente.');

        $this->assertDatabaseHas('ai_flow_steps', [
            'ai_flow_version_id' => $version->id,
            'step_key' => 'diagnostico_inicial',
            'name' => 'Diagnóstico inicial',
            'position' => 1,
        ]);
    }

    public function test_admin_can_edit_step_in_draft_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $step = $this->createStep($version, [
            'step_key' => 'diagnostico',
            'name' => 'Diagnóstico',
            'position' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editStep', $step->id)
            ->set('name', 'Diagnóstico actualizado')
            ->set('expected_output_name', 'Informe actualizado')
            ->set('base_prompt', 'Nuevo prompt {{pais}}.')
            ->call('saveStep')
            ->assertSee('Etapa actualizada correctamente.');

        $this->assertDatabaseHas('ai_flow_steps', [
            'id' => $step->id,
            'name' => 'Diagnóstico actualizado',
            'expected_output_name' => 'Informe actualizado',
            'base_prompt' => 'Nuevo prompt {{pais}}.',
        ]);
    }

    public function test_cannot_create_duplicate_step_key_in_same_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['step_key' => 'diagnostico', 'position' => 1]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', 'diagnostico')
            ->set('name', 'Otro diagnóstico')
            ->set('position', 2)
            ->call('saveStep')
            ->assertHasErrors(['step_key' => 'unique'])
            ->assertSee('Ya existe una etapa con esta clave en la versión.');
    }

    public function test_cannot_create_step_key_with_spaces_or_accents(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', 'diagnóstico inicial')
            ->set('name', 'Diagnóstico')
            ->set('position', 1)
            ->call('saveStep')
            ->assertHasErrors(['step_key' => 'regex'])
            ->assertSee('La clave de la etapa debe estar en minúsculas, sin espacios ni tildes.');
    }

    public function test_non_admin_cannot_access_step_builder(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Cliente');
        $user = $this->createUser('Cliente', $empresa);
        [$flow, $version] = $this->createDraftFlow($admin);

        $this->actingAs($user)
            ->get(route('admin.ai-flows.versions.show', [$flow, $version]))
            ->assertForbidden();
    }

    public function test_cannot_edit_step_when_version_is_published(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $version->forceFill(['status' => AiFlowVersion::STATUS_PUBLISHED])->save();
        $step = $this->createStep($version, ['position' => 1]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editStep', $step->id)
            ->assertSee('Solo se pueden editar etapas en versiones borrador.')
            ->set('step_key', 'no_debe_guardar')
            ->set('name', 'No debe guardar')
            ->set('position', 2)
            ->call('saveStep')
            ->assertSee('Solo se pueden crear o editar etapas en versiones borrador.');

        $this->assertDatabaseMissing('ai_flow_steps', [
            'step_key' => 'no_debe_guardar',
        ]);
    }

    public function test_step_listing_is_ordered_by_position(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['name' => 'Segunda etapa', 'step_key' => 'segunda', 'position' => 2]);
        $this->createStep($version, ['name' => 'Primera etapa', 'step_key' => 'primera', 'position' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.ai-flows.versions.show', [$flow, $version]))
            ->assertOk()
            ->assertSeeInOrder(['Primera etapa', 'Segunda etapa']);
    }

    public function test_parser_preview_shows_detected_variables_from_base_prompt(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('base_prompt', 'Analiza {{pais}} y {{ciudad}}.')
            ->assertSee('Variables detectadas (2)')
            ->assertSee('pais')
            ->assertSee('ciudad');
    }

    public function test_parser_preview_shows_invalid_tokens_from_base_prompt(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('base_prompt', 'Analiza {{País}} y {{nombre variable}}.')
            ->assertSee('Tokens inválidos (2)')
            ->assertSee('País')
            ->assertSee('nombre variable');
    }

    public function test_can_save_dependency_to_previous_step_in_same_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $firstStep = $this->createStep($version, [
            'name' => 'Contexto',
            'step_key' => 'contexto',
            'position' => 1,
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', 'diagnostico')
            ->set('name', 'Diagnóstico')
            ->set('position', 2)
            ->set('depends_on_step_id', (string) $firstStep->id)
            ->call('saveStep')
            ->assertSee('Etapa creada correctamente.');

        $secondStep = AiFlowStep::query()->where('step_key', 'diagnostico')->firstOrFail();
        $this->assertDatabaseHas('ai_flow_step_dependencies', [
            'ai_flow_step_id' => $secondStep->id,
            'depends_on_step_id' => $firstStep->id,
        ]);
    }

    public function test_cannot_save_dependency_to_step_from_another_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin, 'flujo_uno');
        [, $otherVersion] = $this->createDraftFlow($admin, 'flujo_dos');
        $otherStep = $this->createStep($otherVersion, ['position' => 1]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->set('step_key', 'diagnostico')
            ->set('name', 'Diagnóstico')
            ->set('position', 2)
            ->set('depends_on_step_id', (string) $otherStep->id)
            ->call('saveStep')
            ->assertHasErrors(['depends_on_step_id'])
            ->assertSee('La dependencia debe pertenecer a la misma versión.');
    }

    public function test_cannot_save_dependency_to_later_step(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $firstStep = $this->createStep($version, ['step_key' => 'primera', 'position' => 1]);
        $laterStep = $this->createStep($version, ['step_key' => 'segunda', 'position' => 2]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editStep', $firstStep->id)
            ->set('depends_on_step_id', (string) $laterStep->id)
            ->call('saveStep')
            ->assertHasErrors(['depends_on_step_id'])
            ->assertSee('La dependencia debe ser una etapa anterior.');
    }

    public function test_publication_still_fails_when_detected_variables_are_not_configured(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, [
            'step_key' => 'diagnostico',
            'position' => 1,
            'base_prompt' => 'Analiza {{pais}}.',
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('publish')
            ->assertSee('pais')
            ->assertSee('aparece en prompts')
            ->assertSee('configurada');

        $this->assertSame(AiFlowVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion}
     */
    private function createDraftFlow(User $admin, string $key = 'flujo'): array
    {
        $flow = AiFlow::create([
            'key' => $key . '_' . uniqid(),
            'name' => 'Flujo IA',
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
