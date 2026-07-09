<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentImportManager;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentImportManagerMountTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_mount_blocks_user_without_authorized_empresa(): void
    {
        $user = $this->createUser('Cliente', null);
        $this->actingAs($user);

        $component = app(ContentImportManager::class);

        try {
            $component->mount();
            $this->fail('Expected unauthorized mount to abort.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_mount_loads_all_empresas_for_admin(): void
    {
        $empresaA = $this->createEmpresa('Empresa Alfa');
        $empresaB = $this->createEmpresa('Empresa Beta');
        $user = $this->createUser('Administrador');
        $this->actingAs($user);

        /** @var ContentImportManager $component */
        $component = app(ContentImportManager::class);
        $component->mount();

        $this->assertCount(2, $component->authorizedEmpresas);
        $this->assertSame($empresaA->id, $component->selectedEmpresaId);
        $this->assertSame('Empresa Alfa', $component->authorizedEmpresas[0]['nombre']);
        $this->assertSame('Empresa Beta', $component->authorizedEmpresas[1]['nombre']);
        $this->assertNull($component->tone);
    }

    public function test_mount_limits_client_to_visible_empresa_only(): void
    {
        $visibleEmpresa = $this->createEmpresa('Empresa Visible');
        $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $visibleEmpresa);
        $this->actingAs($user);

        /** @var ContentImportManager $component */
        $component = app(ContentImportManager::class);
        $component->mount();

        $this->assertCount(1, $component->authorizedEmpresas);
        $this->assertSame($visibleEmpresa->id, $component->selectedEmpresaId);
        $this->assertSame('Empresa Visible', $component->authorizedEmpresas[0]['nombre']);
    }

    public function test_authorized_user_can_access_import_route(): void
    {
        $empresa = $this->createEmpresa('Empresa Visible');
        $user = $this->createUser('Cliente', $empresa);

        $response = $this->actingAs($user)
            ->get(route('admin.content-management.imports'));

        $response->assertOk();
        $response->assertSee('Gestión de Contenidos - Importación XLSX');
        $response->assertSee('Importación XLSX de contenidos');
        $response->assertSee('Empresa Visible');
    }

    public function test_unauthorized_user_is_blocked_from_import_route(): void
    {
        $user = $this->createUser('Cliente', null);

        $this->actingAs($user)
            ->get(route('admin.content-management.imports'))
            ->assertForbidden();
    }

    public function test_livewire_mount_loads_authorized_empresa_state(): void
    {
        $empresa = $this->createEmpresa('Empresa Única');
        $user = $this->createUser('Cliente', $empresa);

        Livewire::actingAs($user)
            ->test(ContentImportManager::class)
            ->assertSet('selectedEmpresaId', $empresa->id)
            ->assertSet('tone', null)
            ->assertSet('authorizedEmpresas.0.nombre', 'Empresa Única');
    }

    private function createEmpresa(?string $name = null): Empresa
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        return Empresa::create([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => $name ?? 'Empresa Test',
            'direccion' => 'Calle 123',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => 'empresa' . uniqid() . '@test.local',
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
