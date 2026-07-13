<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleDeliveryPublicationPanel;
use App\Models\ContentArticle;
use App\Models\ContentArticleFile;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentFinalFileService;
use App\Services\ContentManagement\ContentArticleReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentArticleDeliveryPublicationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('content_management.final_files.disk', 'local');
    }

    public function test_blocks_delivery_without_final_file(): void
    {
        $empresa = $this->createEmpresa('Empresa Sin Archivo');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithoutFile($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id])
            ->call('markDelivered')
            ->assertHasErrors(['delivery']);

        $article->refresh();
        $this->assertNull($article->delivered_at);
        $this->assertNull($article->delivered_by);
    }

    public function test_marking_delivery_registers_delivered_fields_without_publishing(): void
    {
        $empresa = $this->createEmpresa('Empresa Entrega');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id])
            ->call('markDelivered')
            ->assertHasNoErrors()
            ->assertSee('Entrega registrada correctamente.');

        $article->refresh();

        $this->assertNotNull($article->delivered_at);
        $this->assertSame($user->id, $article->delivered_by);
        $this->assertNull($article->published_at);
        $this->assertNull($article->published_by);
        $this->assertNull($article->published_url);
        $this->assertNotSame(ContentArticle::MAIN_STATUS_PUBLISHED, $article->main_status);
    }

    public function test_unmark_delivery_clears_delivered_fields(): void
    {
        $empresa = $this->createEmpresa('Empresa Desmarcar');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);

        app(ContentArticleReleaseService::class)->markDelivered($article, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->fresh()->id])
            ->call('unmarkDelivered')
            ->assertHasNoErrors()
            ->assertSee('Entrega corregida correctamente.');

        $article->refresh();
        $this->assertNull($article->delivered_at);
        $this->assertNull($article->delivered_by);
    }

    public function test_publication_requires_valid_url(): void
    {
        $empresa = $this->createEmpresa('Empresa URL');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id])
            ->set('publishedUrl', 'no-valida')
            ->call('publishArticle')
            ->assertHasErrors(['publishedUrl']);

        $article->refresh();
        $this->assertNull($article->published_at);
    }

    public function test_publication_registers_published_fields_and_keeps_delivery_independent(): void
    {
        $empresa = $this->createEmpresa('Empresa Publicacion');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id])
            ->set('publishedUrl', 'https://example.com/articulo-publicado')
            ->call('publishArticle')
            ->assertHasNoErrors()
            ->assertSee('Publicacion registrada correctamente.');

        $article->refresh();

        $this->assertNotNull($article->published_at);
        $this->assertSame($user->id, $article->published_by);
        $this->assertSame('https://example.com/articulo-publicado', $article->published_url);
        $this->assertSame(ContentArticle::MAIN_STATUS_PUBLISHED, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_COMPLETED, $article->operational_stage);
        $this->assertNull($article->delivered_at);
        $this->assertNull($article->delivered_by);
    }

    public function test_publication_does_not_alter_existing_delivery(): void
    {
        $empresa = $this->createEmpresa('Empresa Ambas');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);
        $delivered = app(ContentArticleReleaseService::class)->markDelivered($article, $user);
        $deliveredAt = $delivered->delivered_at;

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->fresh()->id])
            ->set('publishedUrl', 'https://example.com/articulo-entregado-publicado')
            ->call('publishArticle')
            ->assertHasNoErrors();

        $article->refresh();

        $this->assertSame($user->id, $article->delivered_by);
        $this->assertEquals($deliveredAt, $article->delivered_at);
        $this->assertSame(ContentArticle::MAIN_STATUS_PUBLISHED, $article->main_status);
    }

    public function test_published_url_can_be_updated_explicitly(): void
    {
        $empresa = $this->createEmpresa('Empresa Update URL');
        $user = $this->createUser('Administrador');
        $article = $this->createCompletedArticleWithFinalFile($empresa, $user);

        app(ContentArticleReleaseService::class)->publish($article, 'https://example.com/original', $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->fresh()->id])
            ->set('publishedUrl', 'https://example.com/actualizada')
            ->call('updatePublishedUrlAction')
            ->assertHasNoErrors()
            ->assertSee('URL publicada actualizada correctamente.');

        $article->refresh();
        $this->assertSame('https://example.com/actualizada', $article->published_url);
    }

    public function test_user_without_access_cannot_mark_delivery(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createCompletedArticleWithFinalFile($empresaVisible, $user);
        $hiddenArticle = $this->createCompletedArticleWithFinalFile($empresaOculta, $this->createUser('Administrador'));

        $this->actingAs($user);

        /** @var ContentArticleDeliveryPublicationPanel $component */
        $component = app(ContentArticleDeliveryPublicationPanel::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;

        try {
            $component->markDelivered(
                app(ContentAccessService::class),
                app(ContentArticleReleaseService::class)
            );

            $this->fail('Expected forbidden delivery action when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_user_without_access_cannot_publish(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createCompletedArticleWithFinalFile($empresaVisible, $user);
        $hiddenArticle = $this->createCompletedArticleWithFinalFile($empresaOculta, $this->createUser('Administrador'));

        $this->actingAs($user);

        /** @var ContentArticleDeliveryPublicationPanel $component */
        $component = app(ContentArticleDeliveryPublicationPanel::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;
        $component->publishedUrl = 'https://example.com/prohibida';

        try {
            $component->publishArticle(
                app(ContentAccessService::class),
                app(ContentArticleReleaseService::class)
            );

            $this->fail('Expected forbidden publish action when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    private function createCompletedArticleWithoutFile(Empresa $empresa, User $user): ContentArticle
    {
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'operational_stage' => ContentArticle::STAGE_COMPLETED,
        ]);

        $this->markObjectiveReady($article, $user);
        $this->markDraftingReady($article, $user);
        $this->markVideoInstagramReady($article, $user);

        return $article->fresh();
    }

    private function createCompletedArticleWithFinalFile(Empresa $empresa, User $user): ContentArticle
    {
        $article = $this->createCompletedArticleWithoutFile($empresa, $user);
        app(ContentFinalFileService::class)->upload($article, $this->makeUploadedPdf(), $user);

        return $article->fresh();
    }

    private function makeUploadedPdf(string $name = 'articulo-final.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        );
    }

    private function markObjectiveReady(ContentArticle $article, User $user): void
    {
        $this->step($article, ContentArticleStep::TYPE_OBJECTIVE)->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinutes(3),
        ])->save();
    }

    private function markDraftingReady(ContentArticle $article, User $user): void
    {
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => ContentArticleStep::TYPE_DRAFTING],
            ['name' => 'Drafting', 'is_active' => true]
        );

        $version = ContentMasterTemplateVersion::query()->firstOrCreate(
            [
                'content_master_template_id' => $template->id,
                'version_number' => 1,
            ],
            [
                'template_body' => 'Prompt drafting base',
                'is_active' => true,
            ]
        );

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $version->id,
            'step_type' => ContentArticleStep::TYPE_DRAFTING,
            'final_prompt_text' => 'Prompt drafting generado',
            'generated_by' => $user->id,
            'generated_at' => now()->subMinutes(2),
        ]);

        $this->step($article, ContentArticleStep::TYPE_DRAFTING)->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinutes(2),
        ])->save();
    }

    private function markVideoInstagramReady(ContentArticle $article, User $user): void
    {
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => ContentArticleStep::TYPE_VIDEO_INSTAGRAM],
            ['name' => 'Video Instagram', 'is_active' => true]
        );

        $version = ContentMasterTemplateVersion::query()->firstOrCreate(
            [
                'content_master_template_id' => $template->id,
                'version_number' => 1,
            ],
            [
                'template_body' => 'Prompt video base',
                'is_active' => true,
            ]
        );

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $version->id,
            'step_type' => ContentArticleStep::TYPE_VIDEO_INSTAGRAM,
            'final_prompt_text' => 'Prompt video generado',
            'generated_by' => $user->id,
            'generated_at' => now()->subMinute(),
        ]);

        $this->step($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM)->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinute(),
        ])->save();
    }

    private function step(ContentArticle $article, string $stepType): ContentArticleStep
    {
        return ContentArticleStep::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', $stepType)
            ->firstOrFail();
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

        $article = ContentArticle::create(array_merge([
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

        foreach (ContentArticleStep::STEP_TYPES as $stepType) {
            ContentArticleStep::create([
                'content_article_id' => $article->id,
                'step_type' => $stepType,
                'step_status' => ContentArticleStep::STATUS_PENDING,
                'ready_at' => null,
                'ready_by' => null,
            ]);
        }

        return $article;
    }
}
