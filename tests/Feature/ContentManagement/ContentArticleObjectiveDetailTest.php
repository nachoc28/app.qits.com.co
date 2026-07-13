<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleObjectiveDetail;
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
use App\Services\ContentManagement\ContentObjectivePromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentArticleObjectiveDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_uses_active_objective_template_version(): void
    {
        $empresa = $this->createEmpresa('Empresa Objective');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user);
        $inactiveVersion = $this->createObjectiveTemplateVersion(1, 'Plantilla inactiva', false);
        $activeVersion = $this->createObjectiveTemplateVersion(2, 'Plantilla activa sobre [ ].', true);

        /** @var ContentObjectivePromptService $service */
        $service = app(ContentObjectivePromptService::class);
        $generation = $service->generate($article, $user);

        $generation->refresh();

        $this->assertSame($activeVersion->id, $generation->content_master_template_version_id);
        $this->assertNotSame($inactiveVersion->id, $generation->content_master_template_version_id);
        $this->assertSame(ContentArticleStep::TYPE_OBJECTIVE, $generation->step_type);
        $this->assertSame(
            "Plantilla activa sobre {$article->topic}." . PHP_EOL . PHP_EOL .
            'Contexto disponible del articulo:' . PHP_EOL .
            'Empresa: ' . $empresa->nombre . PHP_EOL .
            'Tema: ' . $article->topic . PHP_EOL .
            'Objetivo estrategico general: ' . $article->strategic_objective_general . PHP_EOL .
            'Publico objetivo general: ' . $article->target_audience_general,
            $generation->final_prompt_text
        );
    }

    public function test_regenerate_preserves_history_with_independent_rows(): void
    {
        $empresa = $this->createEmpresa('Empresa History');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        /** @var ContentObjectivePromptService $service */
        $service = app(ContentObjectivePromptService::class);
        $first = $service->generate($article, $user);
        $second = $service->generate($article->fresh(), $user);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->count());
    }

    public function test_first_generation_updates_article_and_objective_step_states(): void
    {
        $empresa = $this->createEmpresa('Empresa Flow');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PENDING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ]);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        /** @var ContentObjectivePromptService $service */
        $service = app(ContentObjectivePromptService::class);
        $service->generate($article, $user);

        $article->refresh();
        $objectiveStep = $this->objectiveStep($article);

        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_STRATEGIC_REFINEMENT, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_IN_PROGRESS, $objectiveStep->step_status);
        $this->assertNull($objectiveStep->ready_by);
        $this->assertNull($objectiveStep->ready_at);
    }

    public function test_regeneration_does_not_reset_existing_states(): void
    {
        $empresa = $this->createEmpresa('Empresa No Reset');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
            'operational_stage' => ContentArticle::STAGE_DRAFTING,
            'refined_objective' => 'Objetivo refinado previo',
            'refined_target_audience' => 'Publico refinado previo',
        ]);
        $step = $this->objectiveStep($article);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now()->subHour(),
        ])->save();
        $activeVersion = $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $activeVersion->id,
            'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
            'final_prompt_text' => 'Prompt previo',
            'generated_by' => $user->id,
            'generated_at' => now()->subDay(),
        ]);

        /** @var ContentObjectivePromptService $service */
        $service = app(ContentObjectivePromptService::class);
        $service->generate($article->fresh(), $user);

        $article->refresh();
        $step->refresh();

        $this->assertSame(ContentArticle::MAIN_STATUS_UNPUBLISHED, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_DRAFTING, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
    }

    public function test_started_processing_article_keeps_processing_on_regeneration(): void
    {
        $empresa = $this->createEmpresa('Empresa Started');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_STRATEGIC_REFINEMENT,
        ]);
        $step = $this->objectiveStep($article);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_IN_PROGRESS,
        ])->save();
        $activeVersion = $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $activeVersion->id,
            'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
            'final_prompt_text' => 'Prompt previo',
            'generated_by' => $user->id,
            'generated_at' => now()->subMinute(),
        ]);

        /** @var ContentObjectivePromptService $service */
        $service = app(ContentObjectivePromptService::class);
        $service->generate($article->fresh(), $user);

        $article->refresh();
        $step->refresh();

        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $article->main_status);
        $this->assertSame(ContentArticle::STAGE_STRATEGIC_REFINEMENT, $article->operational_stage);
        $this->assertSame(ContentArticleStep::STATUS_IN_PROGRESS, $step->step_status);
    }

    public function test_user_without_access_cannot_generate_even_if_article_id_is_tampered(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createArticle($empresaVisible, $user);
        $hiddenArticle = $this->createArticle($empresaOculta, $this->createUser('Administrador'));
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        $this->actingAs($user);

        /** @var ContentArticleObjectiveDetail $component */
        $component = app(ContentArticleObjectiveDetail::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;

        try {
            $component->generatePrompt(
                app(ContentAccessService::class),
                app(ContentObjectivePromptService::class)
            );

            $this->fail('Expected forbidden generation when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame(0, ContentArticleGeneration::query()
            ->where('content_article_id', $hiddenArticle->id)
            ->count());
    }

    public function test_missing_active_objective_template_shows_controlled_message_and_logs_detail(): void
    {
        $empresa = $this->createEmpresa('Empresa Missing Template');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective inactiva [ ].', false);

        Log::shouldReceive('error')
            ->once()
            ->with(
                '[CONTENT][PROMPT][OBJECTIVE_TEMPLATE_MISSING] Active template version is not available.',
                Mockery::on(static function (array $context) use ($article, $user): bool {
                    return ($context['step_type'] ?? null) === ContentArticleStep::TYPE_OBJECTIVE
                        && ($context['template_exists'] ?? null) === true
                        && ($context['template_is_active'] ?? null) === true
                        && ($context['versions_count'] ?? null) === 1
                        && ($context['active_versions_count'] ?? null) === 0
                        && ($context['content_article_id'] ?? null) === $article->id
                        && ($context['user_id'] ?? null) === $user->id;
                })
            );

        Livewire::actingAs($user)
            ->test(ContentArticleObjectiveDetail::class, ['articleId' => $article->id])
            ->call('generatePrompt')
            ->assertSee('La plantilla necesaria para este paso no está configurada. Contacta al administrador.');

        $this->assertSame(0, ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->count());
    }

    public function test_detail_shows_spanish_labels_for_statuses_and_steps(): void
    {
        $empresa = $this->createEmpresa('Empresa Labels');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_DRAFTING,
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
        ]);

        $this->objectiveStep($article)->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now(),
        ])->save();

        ContentArticleStep::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->update(['step_status' => ContentArticleStep::STATUS_IN_PROGRESS]);

        $objectiveTemplateVersion = $this->createTemplateVersion(ContentArticleStep::TYPE_OBJECTIVE, 1);
        $draftingTemplateVersion = $this->createTemplateVersion(ContentArticleStep::TYPE_DRAFTING, 1);
        $videoTemplateVersion = $this->createTemplateVersion(ContentArticleStep::TYPE_VIDEO_INSTAGRAM, 1);

        $this->createGeneration($article, $user, $objectiveTemplateVersion, ContentArticleStep::TYPE_OBJECTIVE, 'Prompt objetivo 1');
        $this->createGeneration($article, $user, $draftingTemplateVersion, ContentArticleStep::TYPE_DRAFTING, 'Prompt redaccion 1');
        $this->createGeneration($article, $user, $draftingTemplateVersion, ContentArticleStep::TYPE_DRAFTING, 'Prompt redaccion 2');
        $this->createGeneration($article, $user, $videoTemplateVersion, ContentArticleStep::TYPE_VIDEO_INSTAGRAM, 'Prompt video 1');
        $this->createGeneration($article, $user, $videoTemplateVersion, ContentArticleStep::TYPE_VIDEO_INSTAGRAM, 'Prompt video 2');
        $this->createGeneration($article, $user, $videoTemplateVersion, ContentArticleStep::TYPE_VIDEO_INSTAGRAM, 'Prompt video 3');

        $this->actingAs($user)
            ->get(route('admin.content-management.articles.show', $article))
            ->assertOk()
            ->assertSeeText('Detalle operativo del articulo para avanzar los pasos habilitados del flujo de contenidos.')
            ->assertSee('text-white', false)
            ->assertDontSee('text-emerald-950', false)
            ->assertSee('Navegacion del flujo de gestion de contenidos', false)
            ->assertSee('sticky top-0', false)
            ->assertSee('overflow-x-auto', false)
            ->assertSee('href="#content-step-objective"', false)
            ->assertSee('href="#content-step-drafting"', false)
            ->assertSee('href="#content-step-curation"', false)
            ->assertSee('href="#content-step-video-instagram"', false)
            ->assertSee('href="#content-step-final-file"', false)
            ->assertSee('href="#content-step-release"', false)
            ->assertSee('id="content-step-objective"', false)
            ->assertSee('id="content-step-drafting"', false)
            ->assertSee('id="content-step-curation"', false)
            ->assertSee('id="content-step-video-instagram"', false)
            ->assertSee('id="content-step-final-file"', false)
            ->assertSee('id="content-step-release"', false)
            ->assertSee('scroll-mt-32', false)
            ->assertSeeText('Objetivo y público')
            ->assertSeeText('Entrega / Publicación')
            ->assertSee('border border-blue-100 bg-blue-50', false)
            ->assertSee('border border-emerald-100 bg-emerald-50', false)
            ->assertSee('border border-indigo-100 bg-indigo-50', false)
            ->assertSee('border border-amber-100 bg-amber-50', false)
            ->assertSee('border border-slate-200 bg-slate-50', false)
            ->assertSee('border-cyan-200 bg-cyan-50 text-cyan-800', false)
            ->assertSee('border-l-4 border-emerald-500', false)
            ->assertSee('border-l-4 border-blue-500', false)
            ->assertSee('py-1.5 text-sm font-semibold', false)
            ->assertSeeText('Paso 1')
            ->assertSeeText('Definir objetivo')
            ->assertSeeText('Paso 2')
            ->assertSeeText('Redactar')
            ->assertSeeText('Curado previo del artículo')
            ->assertSeeText('Curado')
            ->assertSeeText('Manual')
            ->assertSeeText('@CuradorDeContenido')
            ->assertSeeText('Abre @CuradorDeContenido en ChatGPT.')
            ->assertSeeText('Adjunta el documento final del artículo en Word o PDF.')
            ->assertSeeText('Solicita la revisión de claridad, coherencia, precisión, tono y alineación estratégica.')
            ->assertSeeText('Aplica los ajustes recomendados en el documento final.')
            ->assertSeeText('Usa el documento curado como insumo para el Paso 3.')
            ->assertSeeText('Este paso es manual y no reemplaza la revisión humana final.')
            ->assertSee('border border-cyan-100 bg-cyan-50', false)
            ->assertSeeText('Paso 3')
            ->assertSeeText('Crear contenido para video e Instagram')
            ->assertSeeText('Archivos finales')
            ->assertSeeText('Entrega manual')
            ->assertSeeText('Publicacion manual')
            ->assertSeeText('GPT recomendado')
            ->assertSeeText('@consultormarketingdigital')
            ->assertSeeText('@redactorSEOGutenber')
            ->assertSeeText('@StorytellingCorporativo')
            ->assertSeeText('Abre este GPT en ChatGPT.')
            ->assertSeeText('Adjunta primero el documento final del art')
            ->assertSeeText('Pega el prompt generado.')
            ->assertSeeText('Ejecuta la consulta.')
            ->assertDontSee('href="@consultormarketingdigital"', false)
            ->assertDontSee('href="@redactorSEOGutenber"', false)
            ->assertDontSee('href="@StorytellingCorporativo"', false)
            ->assertSeeText('Bloqueado')
            ->assertSeeText('En proceso')
            ->assertSeeText('Redacción')
            ->assertSeeText('Objetivo')
            ->assertSeeText('Video e Instagram')
            ->assertSeeText('Listo')
            ->assertSeeText('Copiar prompt')
            ->assertSee('wire:loading.flex wire:target="generatePrompt" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="saveRefinedResults" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="markObjectiveReady" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="markDraftingReady" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="markVideoInstagramReady" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="uploadFinalFile" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="markDelivered" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="unmarkDelivered" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="publishArticle" style="display: none;"', false)
            ->assertSee('wire:loading.flex wire:target="updatePublishedUrlAction" style="display: none;"', false)
            ->assertSeeText('Generando prompt...')
            ->assertSeeText('Guardando...')
            ->assertSeeText('Subiendo archivo...')
            ->assertSeeText('Publicando...')
            ->assertSeeText('Bloqueado:')
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertDontSee('<details open', false)
            ->assertSeeText('Historial de generaciones (1)')
            ->assertSeeText('Historial de generaciones (2)')
            ->assertSeeText('Historial de generaciones (3)')
            ->assertSeeText('Ver prompt')
            ->assertDontSeeText('objective')
            ->assertDontSeeText('drafting')
            ->assertDontSeeText('video_instagram')
            ->assertDontSeeText('ready_at')
            ->assertDontSeeText('ready_by');
    }

    public function test_recommended_gpt_blocks_are_shown_before_prompt_blocks_without_duplicate_video_instruction(): void
    {
        $empresa = $this->createEmpresa('Empresa GPT Order');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user);

        $html = $this->actingAs($user)
            ->get(route('admin.content-management.articles.show', $article))
            ->assertOk()
            ->getContent();

        $prompt1Position = strpos($html, '<h4 class="text-sm font-semibold text-gray-900">Prompt 1</h4>');
        $prompt2Position = strpos($html, '<h4 class="text-sm font-semibold text-gray-900">Prompt 2</h4>');
        $prompt3Position = strpos($html, '<h4 class="text-sm font-semibold text-gray-900">Prompt 3</h4>');
        $gpt1Position = strpos($html, '@consultormarketingdigital');
        $gpt2Position = strpos($html, '@redactorSEOGutenber');
        $gpt3Position = strpos($html, '@StorytellingCorporativo');

        $this->assertNotFalse($prompt1Position);
        $this->assertNotFalse($prompt2Position);
        $this->assertNotFalse($prompt3Position);
        $this->assertNotFalse($gpt1Position);
        $this->assertNotFalse($gpt2Position);
        $this->assertNotFalse($gpt3Position);
        $this->assertLessThan($prompt1Position, $gpt1Position);
        $this->assertLessThan($prompt2Position, $gpt2Position);
        $this->assertLessThan($prompt3Position, $gpt3Position);
        $this->assertSame(1, substr_count($html, 'Adjunta primero el documento final del art'));
        $this->assertStringNotContainsString('href="@consultormarketingdigital"', $html);
        $this->assertStringNotContainsString('href="@redactorSEOGutenber"', $html);
        $this->assertStringNotContainsString('href="@StorytellingCorporativo"', $html);
    }

    public function test_manual_curation_block_is_between_step_two_and_step_three_without_changing_prompt_three(): void
    {
        $empresa = $this->createEmpresa('Empresa Curado');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user);

        $html = $this->actingAs($user)
            ->get(route('admin.content-management.articles.show', $article))
            ->assertOk()
            ->getContent();

        $draftingPosition = strpos($html, 'id="content-step-drafting"');
        $curationPosition = strpos($html, 'id="content-step-curation"');
        $videoPosition = strpos($html, 'id="content-step-video-instagram"');

        $this->assertNotFalse($draftingPosition);
        $this->assertNotFalse($curationPosition);
        $this->assertNotFalse($videoPosition);
        $this->assertLessThan($curationPosition, $draftingPosition);
        $this->assertLessThan($videoPosition, $curationPosition);
        $this->assertStringContainsString('@CuradorDeContenido', $html);
        $this->assertStringContainsString('href="#content-step-curation"', $html);
        $this->assertStringContainsString('id="content_video_instagram_prompt_preview"', $html);
    }

    public function test_saving_refined_fields_preserves_general_fields(): void
    {
        $empresa = $this->createEmpresa('Empresa Save');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'strategic_objective_general' => 'Objetivo general original',
            'target_audience_general' => 'Publico general original',
        ]);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        Livewire::actingAs($user)
            ->test(ContentArticleObjectiveDetail::class, ['articleId' => $article->id])
            ->set('refinedObjective', 'Objetivo refinado nuevo')
            ->set('refinedTargetAudience', 'Publico refinado nuevo')
            ->call('saveRefinedResults')
            ->assertEmitted('contentObjectiveUpdated', $article->id)
            ->assertSee('Resultados guardados');

        $article->refresh();

        $this->assertSame('Objetivo general original', $article->strategic_objective_general);
        $this->assertSame('Publico general original', $article->target_audience_general);
        $this->assertSame('Objetivo refinado nuevo', $article->refined_objective);
        $this->assertSame('Publico refinado nuevo', $article->refined_target_audience);
    }

    public function test_cannot_mark_ready_without_both_refined_fields(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready Block');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => null,
            'refined_target_audience' => null,
        ]);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        Livewire::actingAs($user)
            ->test(ContentArticleObjectiveDetail::class, ['articleId' => $article->id])
            ->set('refinedObjective', 'Solo uno')
            ->set('refinedTargetAudience', '')
            ->call('markObjectiveReady')
            ->assertHasErrors(['refinedTargetAudience' => 'required']);

        $step = $this->objectiveStep($article->fresh());

        $this->assertSame(ContentArticleStep::STATUS_PENDING, $step->step_status);
        $this->assertNull($step->ready_by);
        $this->assertNull($step->ready_at);
    }

    public function test_can_mark_ready_with_both_refined_fields_and_register_audit_fields(): void
    {
        $empresa = $this->createEmpresa('Empresa Ready');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => null,
            'refined_target_audience' => null,
        ]);
        $this->createObjectiveTemplateVersion(1, 'Plantilla objective [ ].', true);

        Livewire::actingAs($user)
            ->test(ContentArticleObjectiveDetail::class, ['articleId' => $article->id])
            ->set('refinedObjective', 'Objetivo refinado final')
            ->set('refinedTargetAudience', 'Publico refinado final')
            ->call('markObjectiveReady')
            ->assertHasNoErrors()
            ->assertEmitted('contentObjectiveUpdated', $article->id)
            ->assertSee('Paso 1 marcado como listo.');

        $article->refresh();
        $step = $this->objectiveStep($article);

        $this->assertSame('Objetivo refinado final', $article->refined_objective);
        $this->assertSame('Publico refinado final', $article->refined_target_audience);
        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
        $this->assertSame($user->id, $step->ready_by);
        $this->assertNotNull($step->ready_at);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function createObjectiveTemplateVersion(
        int $versionNumber,
        string $templateBody,
        bool $isActive
    ): ContentMasterTemplateVersion {
        return $this->createTemplateVersion(
            ContentArticleStep::TYPE_OBJECTIVE,
            $versionNumber,
            $templateBody,
            $isActive
        );
    }

    private function createTemplateVersion(
        string $stepType,
        int $versionNumber,
        string $templateBody = 'Plantilla [ ].',
        bool $isActive = true
    ): ContentMasterTemplateVersion {
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => $stepType],
            [
                'name' => ucfirst(str_replace('_', ' ', $stepType)),
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

    private function createGeneration(
        ContentArticle $article,
        User $user,
        ContentMasterTemplateVersion $templateVersion,
        string $stepType,
        string $promptText
    ): ContentArticleGeneration {
        return ContentArticleGeneration::create([
            'content_article_id' => $article->id,
            'content_master_template_version_id' => $templateVersion->id,
            'step_type' => $stepType,
            'final_prompt_text' => $promptText,
            'generated_by' => $user->id,
            'generated_at' => now(),
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

        $article = ContentArticle::create(array_merge([
            'content_import_id' => $import->id,
            'article_date' => now()->toDateString(),
            'topic' => 'Tema Base',
            'strategic_objective_general' => 'Objetivo base',
            'target_audience_general' => 'Publico base',
            'refined_objective' => null,
            'refined_target_audience' => null,
            'tone' => ContentArticle::TONE_TUTEO,
            'main_status' => ContentArticle::MAIN_STATUS_PENDING,
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

    private function objectiveStep(ContentArticle $article): ContentArticleStep
    {
        return ContentArticleStep::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->firstOrFail();
    }
}
