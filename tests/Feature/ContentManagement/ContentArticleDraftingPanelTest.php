<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleDraftingPanel;
use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Models\ProyectoEmpresa;
use App\Models\TipoUsuario;
use App\Models\TipoProyecto;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentDraftingPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentArticleDraftingPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_drafting_if_objective_is_not_ready(): void
    {
        $empresa = $this->createEmpresa('Empresa Objective Pending');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
        ]);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, 'URL del sitio web: _', true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['drafting']);

        $this->assertSame(0, $this->draftingGenerationCount($article));
    }

    public function test_blocks_if_refined_fields_are_missing(): void
    {
        $empresa = $this->createEmpresa('Empresa Refinados');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $article->forceFill([
            'refined_objective' => null,
            'refined_target_audience' => null,
        ])->save();
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, 'URL del sitio web: _', true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['drafting']);
    }

    public function test_objective_update_event_refreshes_refined_fields_without_reload(): void
    {
        $empresa = $this->createEmpresa('Empresa Refresh Refinados');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $article->forceFill([
            'refined_objective' => null,
            'refined_target_audience' => null,
        ])->save();
        $this->setSiteUrl($empresa, 'https://empresa-refresh.test');

        $component = Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->assertSee('Estado: Bloqueado');

        $article->forceFill([
            'refined_objective' => 'Objetivo refinado actualizado',
            'refined_target_audience' => 'Publico refinado actualizado',
        ])->save();

        $component
            ->emit('contentObjectiveUpdated', $article->id)
            ->assertDontSee('Estado: Bloqueado')
            ->assertSee('Estado: Pendiente');
    }

    public function test_objective_ready_event_enables_drafting_without_reload(): void
    {
        $empresa = $this->createEmpresa('Empresa Refresh Ready');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
        ]);
        $this->setSiteUrl($empresa, 'https://empresa-ready.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        $component = Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->assertSee('Estado: Bloqueado');

        $this->markObjectiveReady($article, $user);

        $component
            ->emit('contentObjectiveUpdated', $article->id)
            ->assertDontSee('Estado: Bloqueado')
            ->call('generatePrompt')
            ->assertHasNoErrors()
            ->assertSee('Prompt 2 generado.');

        $this->assertSame(1, $this->draftingGenerationCount($article));
    }

    public function test_drafting_listener_uses_livewire_2_listeners_array(): void
    {
        $reflection = new ReflectionClass(ContentArticleDraftingPanel::class);
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);

        $component = app(ContentArticleDraftingPanel::class);

        $this->assertSame(
            ['contentObjectiveUpdated' => 'refreshFromObjective'],
            $property->getValue($component)
        );
    }

    public function test_blocks_if_site_url_is_missing(): void
    {
        $empresa = $this->createEmpresa('Empresa Sin URL');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $this->createDraftingTemplateVersion(1, 'URL del sitio web: _', true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['drafting']);
    }

    public function test_does_not_use_wordpress_site_url_as_fallback(): void
    {
        $empresa = $this->createEmpresa('Empresa WordPress');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        EmpresaSeoProperty::create([
            'empresa_id' => $empresa->id,
            'site_url' => '',
            'wordpress_site_url' => 'https://wordpress.test',
            'utm_tracking_enabled' => false,
            'gsc_enabled' => false,
            'ga4_enabled' => false,
        ]);
        $this->createDraftingTemplateVersion(1, 'URL del sitio web: _', true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['drafting']);

        $this->assertSame(0, $this->draftingGenerationCount($article));
    }

    public function test_does_not_use_proyecto_empresa_url_as_fallback(): void
    {
        $empresa = $this->createEmpresa('Empresa Proyecto');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $tipoProyecto = TipoProyecto::create([
            'nombre' => 'Proyecto Test ' . uniqid(),
        ]);
        ProyectoEmpresa::create([
            'empresa_id' => $empresa->id,
            'url' => 'https://proyecto.test',
            'tipo_proyecto_id' => $tipoProyecto->id,
        ]);
        $this->createDraftingTemplateVersion(1, 'URL del sitio web: _', true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['drafting']);

        $this->assertSame(0, $this->draftingGenerationCount($article));
    }

    public function test_uses_exact_active_drafting_version_and_required_dynamic_data(): void
    {
        $empresa = $this->createEmpresa('Empresa Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user, [
            'topic' => 'Tema Drafting',
            'refined_objective' => 'Objetivo refinado final',
            'refined_target_audience' => 'Publico refinado final',
            'tone' => ContentArticle::TONE_USTEO,
        ]);
        $this->setSiteUrl($empresa, 'https://empresa-drafting.test');
        $inactiveVersion = $this->createDraftingTemplateVersion(1, 'Plantilla inactiva', false);
        $activeVersion = $this->createDraftingTemplateVersion(2, $this->draftingTemplateBody(), true);

        /** @var ContentDraftingPromptService $service */
        $service = app(ContentDraftingPromptService::class);
        $generation = $service->generate($article, $user);

        $generation->refresh();

        $this->assertSame($activeVersion->id, $generation->content_master_template_version_id);
        $this->assertNotSame($inactiveVersion->id, $generation->content_master_template_version_id);
        $this->assertStringContainsString('URL del sitio web: https://empresa-drafting.test', $generation->final_prompt_text);
        $this->assertStringContainsString('Tema Drafting', $generation->final_prompt_text);
        $this->assertStringContainsString('Objetivo refinado final', $generation->final_prompt_text);
        $this->assertStringContainsString('Publico refinado final', $generation->final_prompt_text);
        $this->assertStringContainsString('Tono de voz: Usteo', $generation->final_prompt_text);
    }

    public function test_regenerate_preserves_history_with_independent_rows(): void
    {
        $empresa = $this->createEmpresa('Empresa Historial Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        /** @var ContentDraftingPromptService $service */
        $service = app(ContentDraftingPromptService::class);
        $first = $service->generate($article, $user);
        $second = $service->generate($article->fresh(), $user);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $this->draftingGenerationCount($article));
    }

    public function test_first_generation_updates_drafting_stage_and_step(): void
    {
        $empresa = $this->createEmpresa('Empresa Estado Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user, [
            'operational_stage' => ContentArticle::STAGE_STRATEGIC_REFINEMENT,
        ]);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        /** @var ContentDraftingPromptService $service */
        $service = app(ContentDraftingPromptService::class);
        $service->generate($article, $user);

        $article->refresh();
        $step = $this->step($article, ContentArticleStep::TYPE_DRAFTING);

        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_DRAFTING, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_IN_PROGRESS, $step->step_status);
    }

    public function test_regeneration_does_not_reset_states(): void
    {
        $empresa = $this->createEmpresa('Empresa No Reset Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_VIDEO_INSTAGRAM,
        ]);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $activeVersion = $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);
        $step = $this->step($article, ContentArticleStep::TYPE_DRAFTING);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subHour(),
        ])->save();

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $activeVersion->id,
            'step_type' => ContentArticleStep::TYPE_DRAFTING,
            'final_prompt_text' => 'Prompt drafting previo',
            'generated_by' => $user->id,
            'generated_at' => now()->subDay(),
        ]);

        /** @var ContentDraftingPromptService $service */
        $service = app(ContentDraftingPromptService::class);
        $service->generate($article->fresh(), $user);

        $article->refresh();
        $step->refresh();

        $this->assertSame(ContentArticle::STAGE_VIDEO_INSTAGRAM, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
    }

    public function test_user_without_access_cannot_generate(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createReadyForDraftingArticle($empresaVisible, $user);
        $hiddenArticle = $this->createReadyForDraftingArticle($empresaOculta, $this->createUser('Administrador'));
        $this->setSiteUrl($empresaVisible, 'https://visible.test');
        $this->setSiteUrl($empresaOculta, 'https://oculta.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        $this->actingAs($user);

        /** @var ContentArticleDraftingPanel $component */
        $component = app(ContentArticleDraftingPanel::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;

        try {
            $component->generatePrompt(
                app(ContentAccessService::class),
                app(ContentDraftingPromptService::class)
            );

            $this->fail('Expected forbidden drafting generation when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_cannot_mark_ready_without_generation(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready Block Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('markDraftingReady')
            ->assertHasErrors(['drafting'])
            ->assertSee('Error:');

        $step = $this->step($article->fresh(), ContentArticleStep::TYPE_DRAFTING);

        $this->assertSame(ContentArticleStep::STATUS_PENDING, $step->step_status);
        $this->assertNull($step->ready_by);
        $this->assertNull($step->ready_at);
    }

    public function test_can_mark_ready_with_generation_and_updates_operational_stage(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForDraftingArticle($empresa, $user);
        $this->setSiteUrl($empresa, 'https://empresa.test');
        $this->createDraftingTemplateVersion(1, $this->draftingTemplateBody(), true);

        /** @var ContentDraftingPromptService $service */
        $service = app(ContentDraftingPromptService::class);
        $service->generate($article, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleDraftingPanel::class, ['articleId' => $article->id])
            ->call('markDraftingReady')
            ->assertHasNoErrors();

        $article->refresh();
        $step = $this->step($article, ContentArticleStep::TYPE_DRAFTING);

        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
        $this->assertSame($user->id, $step->ready_by);
        $this->assertNotNull($step->ready_at);
        $this->assertSame(ContentArticle::STAGE_VIDEO_INSTAGRAM, $article->operational_stage);
        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
    }

    private function draftingTemplateBody(): string
    {
        return <<<TEXT
REDACCION ARTICULO

URL del sitio web: _
Tema del articulo: _
Objetivo del articulo: _
Publico objetivo del articulo: _
Tono de voz: Tuteo - Usteo
TEXT;
    }

    private function createDraftingTemplateVersion(
        int $versionNumber,
        string $templateBody,
        bool $isActive
    ): ContentMasterTemplateVersion {
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => ContentArticleStep::TYPE_DRAFTING],
            [
                'name' => 'Drafting',
                'is_active' => true,
            ]
        );

        return ContentMasterTemplateVersion::create([
            'content_master_template_id' => $template->id,
            'version_number' => $versionNumber,
            'template_body' => $templateBody,
            'is_active' => $isActive,
        ]);
    }

    private function setSiteUrl(Empresa $empresa, string $siteUrl): EmpresaSeoProperty
    {
        return EmpresaSeoProperty::updateOrCreate(
            ['empresa_id' => $empresa->id],
            [
                'site_url' => $siteUrl,
                'wordpress_site_url' => null,
                'utm_tracking_enabled' => false,
                'gsc_enabled' => false,
                'ga4_enabled' => false,
            ]
        );
    }

    private function createReadyForDraftingArticle(Empresa $empresa, User $user, array $attributes = []): ContentArticle
    {
        $article = $this->createArticle($empresa, $user, array_merge([
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
            'operational_stage' => ContentArticle::STAGE_STRATEGIC_REFINEMENT,
        ], $attributes));

        $this->markObjectiveReady($article, $user);

        return $article->fresh();
    }

    private function markObjectiveReady(ContentArticle $article, User $user): void
    {
        $step = $this->step($article, ContentArticleStep::TYPE_OBJECTIVE);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinute(),
        ])->save();
    }

    private function draftingGenerationCount(ContentArticle $article): int
    {
        return ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->count();
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
