<?php

namespace Tests\Feature\ContentManagement;

use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentManagementNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_sees_content_management_navigation_link(): void
    {
        $this->createEmpresa('Empresa Navegacion');
        $user = $this->createUser('Administrador');

        $response = $this->actingAs($user)
            ->get(route('admin.content-management.index'));

        $response->assertOk();
        $response->assertSee('Gestión de Contenidos');
        $response->assertSee(route('admin.content-management.index'), false);
    }

    public function test_content_management_navigation_is_active_on_index_and_subroutes(): void
    {
        $this->createEmpresa('Empresa Activa');
        $user = $this->createUser('Administrador');

        $indexResponse = $this->actingAs($user)
            ->get(route('admin.content-management.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('border-b-2 border-indigo-400', false);
        $indexResponse->assertSee('border-l-4 border-indigo-400', false);

        $importsResponse = $this->actingAs($user)
            ->get(route('admin.content-management.imports'));

        $importsResponse->assertOk();
        $importsResponse->assertSee('border-b-2 border-indigo-400', false);
        $importsResponse->assertSee('border-l-4 border-indigo-400', false);
    }

    public function test_navigation_changes_do_not_break_dashboard_or_seo_entry(): void
    {
        $empresa = $this->createEmpresa('Empresa SEO');
        $user = $this->createUser('Administrador');

        $this->actingAs($user)
            ->get(route('admin.empresas'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('SEO')
            ->assertSee('Gestión de Contenidos');

        $this->actingAs($user)
            ->get(route('admin.seo'))
            ->assertRedirect(route('admin.seo.empresa-dashboard', $empresa));
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

    private function createUser(string $roleName): User
    {
        $tipoUsuario = TipoUsuario::query()->firstOrCreate([
            'nombre' => $roleName,
        ]);

        return User::create([
            'name' => 'Usuario Test ' . uniqid(),
            'email' => 'user' . uniqid() . '@test.local',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'empresa_id' => null,
            'tipo_usuario_id' => $tipoUsuario->id,
            'active' => true,
        ]);
    }
}
