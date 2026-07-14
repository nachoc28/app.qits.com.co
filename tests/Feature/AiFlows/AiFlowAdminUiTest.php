<?php

namespace Tests\Feature\AiFlows;

use App\Http\Livewire\Admin\AiFlows\AiFlowForm;
use App\Http\Livewire\Admin\AiFlows\AiFlowVersionIndex;
use App\Http\Livewire\Admin\AiFlows\AiFlowVersionShow;
use App\Models\AiFlow;
use App\Models\AiFlowStep;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiFlowAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_flow_index(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin, ['name' => 'Investigacion de Mercado']);

        $this->actingAs($admin)
            ->get(route('admin.ai-flows.index'))
            ->assertOk()
            ->assertSee('Flujos IA')
            ->assertSee('Investigacion de Mercado')
            ->assertSee($flow->key)
            ->assertSee('Categoría')
            ->assertSee('Ver versiones');
    }

    public function test_non_admin_cannot_access_ai_flows(): void
    {
        $empresa = $this->createEmpresa('Empresa Cliente');
        $user = $this->createUser('Cliente', $empresa);

        $this->actingAs($user)
            ->get(route('admin.ai-flows.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_flow(): void
    {
        $admin = $this->createUser('Administrador');

        Livewire::actingAs($admin)
            ->test(AiFlowForm::class)
            ->set('name', 'Investigacion de Mercado')
            ->set('key', 'investigacion_mercado')
            ->set('description', 'Flujo para investigar mercado')
            ->set('is_active', true)
            ->call('save')
            ->assertSee('Flujo IA creado correctamente.');

        $this->assertDatabaseHas('ai_flows', [
            'key' => 'investigacion_mercado',
            'name' => 'Investigacion de Mercado',
            'created_by' => $admin->id,
        ]);
    }

    public function test_flow_cannot_be_created_with_duplicate_key(): void
    {
        $admin = $this->createUser('Administrador');
        $this->createFlow($admin, ['key' => 'investigacion_mercado']);

        Livewire::actingAs($admin)
            ->test(AiFlowForm::class)
            ->set('name', 'Otro flujo')
            ->set('key', 'investigacion_mercado')
            ->call('save')
            ->assertHasErrors(['key' => 'unique'])
            ->assertSee('Ya existe un flujo con esta clave.');
    }

    public function test_admin_can_edit_flow(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin, [
            'name' => 'Flujo Original',
            'description' => 'Descripcion original',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowForm::class, ['flowId' => $flow->id])
            ->set('name', 'Flujo Actualizado')
            ->set('description', 'Descripcion actualizada')
            ->set('is_active', false)
            ->call('save')
            ->assertSee('Flujo IA actualizado correctamente.');

        $this->assertDatabaseHas('ai_flows', [
            'id' => $flow->id,
            'name' => 'Flujo Actualizado',
            'description' => 'Descripcion actualizada',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_create_draft_version(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionIndex::class, ['flowId' => $flow->id])
            ->call('createDraftVersion')
            ->assertSee('Versión borrador creada correctamente.');

        $this->assertDatabaseHas('ai_flow_versions', [
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);
    }

    public function test_version_listing_shows_spanish_status_labels(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin);
        AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-flows.versions.index', $flow))
            ->assertOk()
            ->assertSee('Borrador')
            ->assertDontSee('draft');
    }

    public function test_invalid_version_publication_shows_service_errors(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin);
        $version = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, [
                'flowId' => $flow->id,
                'versionId' => $version->id,
            ])
            ->call('publish')
            ->assertSee('La versión debe tener al menos una etapa activa.');

        $this->assertSame(AiFlowVersion::STATUS_DRAFT, $version->fresh()->status);
    }

    public function test_valid_version_publication_uses_service_and_publishes(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin);
        $version = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);
        $step = $this->createStep($version, 'Analiza {{pais}}.');
        $this->createVariable($version, $step, 'pais');

        Livewire::actingAs($admin)
            ->test(AiFlowVersionShow::class, [
                'flowId' => $flow->id,
                'versionId' => $version->id,
            ])
            ->call('publish')
            ->assertSee('Versión publicada correctamente.');

        $version = $version->fresh();
        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $version->status);
        $this->assertNotNull($version->published_at);
        $this->assertSame((int) $admin->id, (int) $version->published_by);
    }

    public function test_only_one_version_remains_published_from_admin_ui(): void
    {
        $admin = $this->createUser('Administrador');
        $flow = $this->createFlow($admin);
        $published = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'published_by' => $admin->id,
        ]);
        $draft = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 2,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);
        $step = $this->createStep($draft, 'Analiza {{pais}}.');
        $this->createVariable($draft, $step, 'pais');

        Livewire::actingAs($admin)
            ->test(AiFlowVersionIndex::class, ['flowId' => $flow->id])
            ->call('publishVersion', $draft->id)
            ->assertSee('Versión publicada correctamente.');

        $this->assertSame(AiFlowVersion::STATUS_ARCHIVED, $published->fresh()->status);
        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $draft->fresh()->status);
        $this->assertSame(1, AiFlowVersion::query()
            ->where('ai_flow_id', $flow->id)
            ->where('status', AiFlowVersion::STATUS_PUBLISHED)
            ->count());
    }

    public function test_navigation_menu_contains_ai_flows(): void
    {
        $admin = $this->createUser('Administrador');

        $this->actingAs($admin)
            ->get(route('admin.ai-flows.index'))
            ->assertOk()
            ->assertSee('Flujos IA')
            ->assertSee(route('admin.ai-flows.index'), false);
    }

    public function test_content_management_still_responds(): void
    {
        $this->createEmpresa('Empresa Contenidos');
        $admin = $this->createUser('Administrador');

        $this->actingAs($admin)
            ->get(route('admin.content-management.index'))
            ->assertOk()
            ->assertSee('Gestión de Contenidos');
    }

    private function createFlow(User $admin, array $attributes = []): AiFlow
    {
        return AiFlow::create(array_merge([
            'key' => 'flujo_' . uniqid(),
            'name' => 'Flujo IA',
            'description' => 'Descripcion base',
            'is_active' => true,
            'created_by' => $admin->id,
        ], $attributes));
    }

    private function createStep(AiFlowVersion $version, string $basePrompt): AiFlowStep
    {
        return AiFlowStep::create([
            'ai_flow_version_id' => $version->id,
            'step_key' => 'diagnostico_' . uniqid(),
            'name' => 'Diagnostico inicial',
            'description' => 'Etapa base',
            'position' => 1,
            'recommended_gpt' => '@InvestigadorMercado',
            'expected_output_name' => 'Diagnostico',
            'base_prompt' => $basePrompt,
            'is_active' => true,
        ]);
    }

    private function createVariable(AiFlowVersion $version, AiFlowStep $step, string $name): AiFlowVariable
    {
        return AiFlowVariable::create([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => null,
            'name' => $name,
            'label' => ucfirst($name),
            'scope' => AiFlowVariable::SCOPE_STEP,
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
            'is_required' => true,
            'position' => 1,
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
