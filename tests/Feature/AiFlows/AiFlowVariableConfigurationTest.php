<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow;
use App\Models\AiFlow;
use App\Models\AiFlowStep;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowVariableConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sync_detected_variables(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, [
            'base_prompt' => 'Analiza {{pais}} y {{objetivo_estrategico}}.',
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('syncVariables')
            ->assertSee('Variables nuevas creadas: 2');

        $this->assertDatabaseHas('ai_flow_variables', [
            'ai_flow_version_id' => $version->id,
            'name' => 'pais',
            'label' => 'Pais',
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
        ]);

        $this->assertDatabaseHas('ai_flow_variables', [
            'ai_flow_version_id' => $version->id,
            'name' => 'objetivo_estrategico',
            'label' => 'Objetivo estrategico',
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
        ]);
    }

    public function test_sync_does_not_duplicate_existing_variables(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('syncVariables')
            ->call('syncVariables')
            ->assertSee('No había variables nuevas.');

        $this->assertSame(1, AiFlowVariable::query()
            ->where('ai_flow_version_id', $version->id)
            ->where('name', 'pais')
            ->count());
    }

    public function test_unused_variable_is_identifiable(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $this->createVariable($version, ['name' => 'variable_obsoleta']);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->assertSee('variable_obsoleta')
            ->assertSee('No usada');
    }

    public function test_invalid_tokens_are_visible_in_variable_section(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['base_prompt' => 'Analiza {{Pais}} y {{nombre variable}}.']);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->assertSee('Tokens')
            ->assertSee('detectados')
            ->assertSee('Pais')
            ->assertSee('nombre variable');
    }

    public function test_admin_can_edit_variable_configuration_in_draft_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $variable = $this->createVariable($version, ['name' => 'pais']);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $variable->id)
            ->set('variable_label', 'Pais objetivo')
            ->set('variable_input_type', AiFlowVariable::INPUT_TYPE_TEXTAREA)
            ->set('variable_scope', AiFlowVariable::SCOPE_STEP)
            ->set('variable_ai_flow_step_id', (string) $step->id)
            ->set('variable_is_required', false)
            ->set('variable_help_text', 'Ayuda operativa')
            ->set('variable_placeholder', 'Colombia')
            ->set('variable_default_value', 'Colombia')
            ->set('variable_position', 3)
            ->call('saveVariable')
            ->assertSee('Variable actualizada correctamente.');

        $this->assertDatabaseHas('ai_flow_variables', [
            'id' => $variable->id,
            'label' => 'Pais objetivo',
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'scope' => AiFlowVariable::SCOPE_STEP,
            'ai_flow_step_id' => $step->id,
            'is_required' => false,
            'position' => 3,
        ]);
    }

    public function test_cannot_edit_variable_in_published_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $variable = $this->createVariable($version, ['name' => 'pais']);
        $version->forceFill(['status' => AiFlowVersion::STATUS_PUBLISHED])->save();

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $variable->id)
            ->assertSee('Solo se pueden editar variables en versiones borrador.')
            ->call('saveVariable')
            ->assertSee('Solo se pueden editar variables en versiones borrador.');
    }

    public function test_step_scope_requires_step_from_same_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin, 'flujo_uno');
        [, $otherVersion] = $this->createDraftFlow($admin, 'flujo_dos');
        $variable = $this->createVariable($version, ['name' => 'pais']);
        $otherStep = $this->createStep($otherVersion);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $variable->id)
            ->set('variable_scope', AiFlowVariable::SCOPE_STEP)
            ->set('variable_ai_flow_step_id', (string) $otherStep->id)
            ->call('saveVariable')
            ->assertHasErrors(['variable_ai_flow_step_id'])
            ->assertSee('La etapa debe pertenecer a la misma versión.');
    }

    public function test_output_scope_requires_source_step_from_same_version(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin, 'flujo_uno');
        [, $otherVersion] = $this->createDraftFlow($admin, 'flujo_dos');
        $variable = $this->createVariable($version, ['name' => 'resultado_previo']);
        $otherStep = $this->createStep($otherVersion);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $variable->id)
            ->set('variable_scope', AiFlowVariable::SCOPE_OUTPUT)
            ->set('variable_source_step_id', (string) $otherStep->id)
            ->call('saveVariable')
            ->assertHasErrors(['variable_source_step_id'])
            ->assertSee('La etapa fuente debe pertenecer a la misma versión.');
    }

    public function test_global_scope_cleans_step_and_source_references(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $step = $this->createStep($version);
        $variable = $this->createVariable($version, [
            'name' => 'pais',
            'scope' => AiFlowVariable::SCOPE_STEP,
            'ai_flow_step_id' => $step->id,
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('editVariable', $variable->id)
            ->set('variable_scope', AiFlowVariable::SCOPE_GLOBAL)
            ->set('variable_source_step_id', (string) $step->id)
            ->call('saveVariable')
            ->assertSee('Variable actualizada correctamente.');

        $variable->refresh();
        $this->assertSame(AiFlowVariable::SCOPE_GLOBAL, $variable->scope);
        $this->assertNull($variable->ai_flow_step_id);
        $this->assertNull($variable->source_step_id);
    }

    public function test_publication_fails_without_configured_variables_and_succeeds_after_sync(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('publish')
            ->assertSee('pais')
            ->call('syncVariables')
            ->call('publish')
            ->assertSee('Versión publicada correctamente.');

        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $version->fresh()->status);
    }

    public function test_version_detail_renders_spanish_utf8_labels_without_mojibake(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $version] = $this->createDraftFlow($admin);
        $this->createStep($version, [
            'base_prompt' => 'Analiza {{pais}} y revisa {{Pais}}.',
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, ['flowId' => $flow->id, 'versionId' => $version->id])
            ->call('syncVariables')
            ->assertSee('versión')
            ->assertSee('Posición')
            ->assertSee('Sí')
            ->assertSee('Tokens inválidos')
            ->assertDontSee('versiÃ')
            ->assertDontSee('PosiciÃ')
            ->assertDontSee('SÃ')
            ->assertDontSee('Ã')
            ->assertDontSee('Â');
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createVariable(AiFlowVersion $version, array $attributes = []): AiFlowVariable
    {
        return AiFlowVariable::create(array_merge([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => null,
            'source_step_id' => null,
            'name' => 'pais_' . uniqid(),
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
