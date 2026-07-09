<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleIndex;
use App\Models\ContentArticle;
use App\Models\ContentImport;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ContentArticleIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 7, 9, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_non_admin_only_sees_articles_from_own_empresa(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);

        $this->createArticle($empresaVisible, $user, [
            'topic' => 'Tema Visible',
        ]);
        $this->createArticle($empresaOculta, $user, [
            'topic' => 'Tema Oculto',
        ]);

        $this->actingAs($user)
            ->get(route('admin.content-management.index'))
            ->assertOk()
            ->assertSee('Tema Visible')
            ->assertDontSee('Tema Oculto');
    }

    public function test_search_filters_by_topic(): void
    {
        $empresa = $this->createEmpresa('Empresa Uno');
        $user = $this->createUser('Administrador');

        $this->createArticle($empresa, $user, ['topic' => 'Marketing Editorial']);
        $this->createArticle($empresa, $user, ['topic' => 'Reporte SEO']);

        Livewire::actingAs($user)
            ->test(ContentArticleIndex::class)
            ->set('search', 'Editorial')
            ->assertSee('Marketing Editorial')
            ->assertDontSee('Reporte SEO');
    }

    public function test_search_filters_by_empresa_name(): void
    {
        $empresaA = $this->createEmpresa('Empresa Alfa');
        $empresaB = $this->createEmpresa('Empresa Beta');
        $user = $this->createUser('Administrador');

        $this->createArticle($empresaA, $user, ['topic' => 'Tema Alfa']);
        $this->createArticle($empresaB, $user, ['topic' => 'Tema Beta']);

        Livewire::actingAs($user)
            ->test(ContentArticleIndex::class)
            ->set('search', 'Beta')
            ->assertSee('Tema Beta')
            ->assertDontSee('Tema Alfa');
    }

    public function test_combined_filters_limit_results(): void
    {
        $empresaA = $this->createEmpresa('Empresa Alfa');
        $empresaB = $this->createEmpresa('Empresa Beta');
        $user = $this->createUser('Administrador');

        $this->createArticle($empresaA, $user, [
            'topic' => 'Tema Actual Alfa',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'article_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        ]);
        $this->createArticle($empresaA, $user, [
            'topic' => 'Tema Publicado Alfa',
            'main_status' => ContentArticle::MAIN_STATUS_PUBLISHED,
            'article_date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'published_at' => now()->subDay(),
        ]);
        $this->createArticle($empresaB, $user, [
            'topic' => 'Tema Actual Beta',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'article_date' => now()->startOfMonth()->addDays(4)->toDateString(),
        ]);

        Livewire::actingAs($user)
            ->test(ContentArticleIndex::class)
            ->set('selectedEmpresaId', (string) $empresaA->id)
            ->set('selectedMainStatus', ContentArticle::MAIN_STATUS_UNPUBLISHED)
            ->set('selectedPeriod', ContentArticleIndex::PERIOD_CURRENT_MONTH)
            ->assertSee('Tema Actual Alfa')
            ->assertDontSee('Tema Publicado Alfa')
            ->assertDontSee('Tema Actual Beta');
    }

    public function test_priority_order_matches_required_buckets(): void
    {
        $empresa = $this->createEmpresa('Empresa Orden');
        $user = $this->createUser('Administrador');

        $this->createArticle($empresa, $user, [
            'topic' => 'Publicado Reciente',
            'main_status' => ContentArticle::MAIN_STATUS_PUBLISHED,
            'article_date' => now()->copy()->subMonth()->startOfMonth()->toDateString(),
            'published_at' => now()->subHour(),
        ]);
        $this->createArticle($empresa, $user, [
            'topic' => 'Pendiente Mes Actual',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'article_date' => now()->copy()->startOfMonth()->addDays(1)->toDateString(),
        ]);
        $this->createArticle($empresa, $user, [
            'topic' => 'Pendiente Otro Mes',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'article_date' => now()->copy()->addMonth()->startOfMonth()->addDays(1)->toDateString(),
        ]);
        $processing = $this->createArticle($empresa, $user, [
            'topic' => 'En Proceso Primero',
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_DRAFTING,
        ]);
        $processing->forceFill([
            'updated_at' => now()->copy()->addMinutes(5),
        ])->save();

        $this->actingAs($user)
            ->get(route('admin.content-management.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'En Proceso Primero',
                'Pendiente Mes Actual',
                'Pendiente Otro Mes',
                'Publicado Reciente',
            ]);
    }

    public function test_pagination_limits_first_page_results(): void
    {
        $empresa = $this->createEmpresa('Empresa Paginacion');
        $user = $this->createUser('Administrador');

        for ($day = 1; $day <= 11; $day++) {
            $this->createArticle($empresa, $user, [
                'topic' => sprintf('Tema Paginado %02d', $day),
                'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
                'article_date' => now()->copy()->startOfMonth()->addDays($day - 1)->toDateString(),
            ]);
        }

        Livewire::actingAs($user)
            ->test(ContentArticleIndex::class)
            ->set('perPage', 10)
            ->assertSee('Tema Paginado 01')
            ->assertSee('Tema Paginado 10')
            ->assertDontSee('Tema Paginado 11')
            ->call('gotoPage', 2)
            ->assertSee('Tema Paginado 11')
            ->assertDontSee('Tema Paginado 01');
    }

    public function test_authorized_user_can_access_article_index_route(): void
    {
        $empresa = $this->createEmpresa('Empresa Acceso');
        $user = $this->createUser('Cliente', $empresa);

        $this->createArticle($empresa, $user, ['topic' => 'Tema Acceso']);

        $this->actingAs($user)
            ->get(route('admin.content-management.index'))
            ->assertOk()
            ->assertSee('Gestión de Contenidos')
            ->assertSee('Tema Acceso');
    }

    public function test_user_cannot_access_article_from_other_empresa(): void
    {
        $empresaA = $this->createEmpresa('Empresa A');
        $empresaB = $this->createEmpresa('Empresa B');
        $admin = $this->createUser('Administrador');
        $user = $this->createUser('Cliente', $empresaA);

        $article = $this->createArticle($empresaB, $admin, ['topic' => 'Tema Restringido']);

        $this->actingAs($user)
            ->get(route('admin.content-management.articles.show', $article))
            ->assertForbidden();
    }

    public function test_listing_reflects_delivery_and_publication_independently(): void
    {
        $empresa = $this->createEmpresa('Empresa Estados Finales');
        $user = $this->createUser('Administrador');

        $this->createArticle($empresa, $user, [
            'topic' => 'Entregado No Publicado',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'operational_stage' => ContentArticle::STAGE_COMPLETED,
            'delivered_at' => now()->subHour(),
            'delivered_by' => $user->id,
            'published_at' => null,
            'published_by' => null,
            'published_url' => null,
        ]);

        $this->createArticle($empresa, $user, [
            'topic' => 'Publicado No Entregado',
            'main_status' => ContentArticle::MAIN_STATUS_PUBLISHED,
            'operational_stage' => ContentArticle::STAGE_COMPLETED,
            'delivered_at' => null,
            'delivered_by' => null,
            'published_at' => now()->subMinutes(30),
            'published_by' => $user->id,
            'published_url' => 'https://example.com/publicado-no-entregado',
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.content-management.index'));

        $response->assertOk();
        $response->assertSee('Entregado No Publicado');
        $response->assertSee('Publicado No Entregado');
        $response->assertSeeTextInOrder([
            'Entregado No Publicado',
            'Sí',
            'No',
        ]);
        $response->assertSeeTextInOrder([
            'Publicado No Entregado',
            'No',
            'Sí',
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createArticle(Empresa $empresa, User $user, array $attributes = []): ContentArticle
    {
        $import = ContentImport::create([
            'empresa_id' => $empresa->id,
            'import_name' => 'Import ' . uniqid(),
            'source_file_name' => 'content.xlsx',
            'imported_by' => $user->id,
            'imported_at' => now(),
        ]);

        return ContentArticle::create(array_merge([
            'content_import_id' => $import->id,
            'article_date' => now()->toDateString(),
            'topic' => 'Tema Base',
            'strategic_objective_general' => 'Objetivo base',
            'target_audience_general' => 'Publico base',
            'refined_objective' => null,
            'refined_target_audience' => null,
            'tone' => ContentArticle::TONE_TUTEO,
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
            'delivered_at' => null,
            'delivered_by' => null,
            'published_at' => null,
            'published_by' => null,
            'published_url' => null,
        ], $attributes));
    }
}
