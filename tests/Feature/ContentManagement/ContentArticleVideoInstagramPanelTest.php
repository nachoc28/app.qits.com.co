<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleVideoInstagramPanel;
use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentVideoInstagramPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentArticleVideoInstagramPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_if_drafting_is_not_ready(): void
    {
        $empresa = $this->createEmpresa('Empresa Drafting Pending');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'operational_stage' => ContentArticle::STAGE_DRAFTING,
        ]);
        $this->createVideoInstagramTemplateVersion(1, 'Plantilla video instagram', true);

        Livewire::actingAs($user)
            ->test(ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['video_instagram']);

        $this->assertSame(0, $this->videoGenerationCount($article));
    }

    public function test_blocks_if_drafting_generation_does_not_exist(): void
    {
        $empresa = $this->createEmpresa('Empresa Sin Drafting');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user, false);
        $this->createVideoInstagramTemplateVersion(1, 'Plantilla video instagram', true);

        Livewire::actingAs($user)
            ->test(ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertHasErrors(['video_instagram']);
    }

    public function test_uses_exact_active_video_instagram_version(): void
    {
        $empresa = $this->createEmpresa('Empresa Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user);
        $inactiveVersion = $this->createVideoInstagramTemplateVersion(1, 'Plantilla inactiva', false);
        $activeVersion = $this->createVideoInstagramTemplateVersion(2, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $generation = $service->generate($article, $user);

        $generation->refresh();

        $this->assertSame($activeVersion->id, $generation->content_master_template_version_id);
        $this->assertNotSame($inactiveVersion->id, $generation->content_master_template_version_id);
    }

    public function test_prompt_includes_explicit_word_or_pdf_instruction(): void
    {
        $empresa = $this->createEmpresa('Empresa Instruction');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user, true, [
            'topic' => 'Tema Video',
        ]);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $generation = $service->generate($article, $user);

        $this->assertStringContainsString(
            'Adjunta en ChatGPT el documento final del articulo en formato Word o PDF antes de ejecutar este prompt.',
            $generation->final_prompt_text
        );
        $this->assertStringContainsString('Tema: Tema Video', $generation->final_prompt_text);
    }

    public function test_prompt_does_not_invent_final_article_content(): void
    {
        $empresa = $this->createEmpresa('Empresa No Invent');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user, true, [
            'topic' => 'Tema Sin Inventar',
        ]);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $prompt = $service->buildPrompt($article);

        $this->assertStringContainsString('No se adjunta automaticamente el contenido final del articulo ni se simula su lectura.', $prompt);
        $this->assertStringNotContainsString('Contenido final del articulo:', $prompt);
        $this->assertStringNotContainsString('Texto del articulo:', $prompt);
    }

    public function test_saves_independent_generation_and_regeneration_history(): void
    {
        $empresa = $this->createEmpresa('Empresa Historial Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $first = $service->generate($article, $user);
        $second = $service->generate($article->fresh(), $user);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $this->videoGenerationCount($article));
    }

    public function test_first_generation_updates_video_instagram_stage_and_step(): void
    {
        $empresa = $this->createEmpresa('Empresa Estado Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $service->generate($article, $user);

        $article->refresh();
        $step = $this->step($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);

        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_VIDEO_INSTAGRAM, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_IN_PROGRESS, $step->step_status);
    }

    public function test_regeneration_does_not_reset_states(): void
    {
        $empresa = $this->createEmpresa('Empresa No Reset Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user);
        $activeVersion = $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);
        $step = $this->step($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subHour(),
        ])->save();
        $article->forceFill([
            'operational_stage' => ContentArticle::STAGE_FINAL_FILE,
        ])->save();

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $activeVersion->id,
            'step_type' => ContentArticleStep::TYPE_VIDEO_INSTAGRAM,
            'final_prompt_text' => 'Prompt video previo',
            'generated_by' => $user->id,
            'generated_at' => now()->subDay(),
        ]);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $service->generate($article->fresh(), $user);

        $article->refresh();
        $step->refresh();

        $this->assertSame(ContentArticle::STAGE_FINAL_FILE, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
    }

    public function test_user_without_access_cannot_generate(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createReadyForVideoInstagramArticle($empresaVisible, $user);
        $hiddenArticle = $this->createReadyForVideoInstagramArticle($empresaOculta, $this->createUser('Administrador'));
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        $this->actingAs($user);

        /** @var ContentArticleVideoInstagramPanel $component */
        $component = app(ContentArticleVideoInstagramPanel::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;

        try {
            $component->generatePrompt(
                app(ContentAccessService::class),
                app(ContentVideoInstagramPromptService::class)
            );

            $this->fail('Expected forbidden video_instagram generation when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_cannot_mark_ready_without_generation(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready Block Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user, false);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        Livewire::actingAs($user)
            ->test(ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id])
            ->call('markVideoInstagramReady')
            ->assertHasErrors(['video_instagram']);

        $step = $this->step($article->fresh(), ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $this->assertSame(ContentArticleStep::STATUS_PENDING, $step->step_status);
        $this->assertNull($step->ready_by);
        $this->assertNull($step->ready_at);
    }

    public function test_can_mark_ready_with_generation_and_updates_final_file_stage(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready Video');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForVideoInstagramArticle($empresa, $user);
        $this->createVideoInstagramTemplateVersion(1, 'PROMPT VIDEOS E INSTAGRAM', true);

        /** @var ContentVideoInstagramPromptService $service */
        $service = app(ContentVideoInstagramPromptService::class);
        $service->generate($article, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id])
            ->call('markVideoInstagramReady')
            ->assertHasNoErrors();

        $article->refresh();
        $step = $this->step($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);

        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
        $this->assertSame($user->id, $step->ready_by);
        $this->assertNotNull($step->ready_at);
        $this->assertSame(ContentArticle::STAGE_FINAL_FILE, $article->operational_stage);
        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
    }

    private function createVideoInstagramTemplateVersion(
        int $versionNumber,
        string $templateBody,
        bool $isActive
    ): ContentMasterTemplateVersion {
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => ContentArticleStep::TYPE_VIDEO_INSTAGRAM],
            [
                'name' => 'Video Instagram',
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

    private function createReadyForVideoInstagramArticle(
        Empresa $empresa,
        User $user,
        bool $withDraftingGeneration = true,
        array $attributes = []
    ): ContentArticle {
        $article = $this->createArticle($empresa, $user, array_merge([
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
            'operational_stage' => ContentArticle::STAGE_VIDEO_INSTAGRAM,
        ], $attributes));

        $this->markObjectiveReady($article, $user);
        $this->markDraftingReady($article, $user, $withDraftingGeneration);

        return $article->fresh();
    }

    private function markObjectiveReady(ContentArticle $article, User $user): void
    {
        $step = $this->step($article, ContentArticleStep::TYPE_OBJECTIVE);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinutes(2),
        ])->save();
    }

    private function markDraftingReady(ContentArticle $article, User $user, bool $withGeneration): void
    {
        if ($withGeneration) {
            $template = ContentMasterTemplate::query()->firstOrCreate(
                ['key' => ContentArticleStep::TYPE_DRAFTING],
                [
                    'name' => 'Drafting',
                    'is_active' => true,
                ]
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
                'generated_at' => now()->subMinute(),
            ]);
        }

        $step = $this->step($article, ContentArticleStep::TYPE_DRAFTING);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subMinute(),
        ])->save();
    }

    private function videoGenerationCount(ContentArticle $article): int
    {
        return ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
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
