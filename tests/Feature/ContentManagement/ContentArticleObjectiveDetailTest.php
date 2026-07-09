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
use Livewire\Livewire;
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
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
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
            ->call('saveRefinedResults');

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
            ->assertHasNoErrors();

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
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => ContentArticleStep::TYPE_OBJECTIVE],
            [
                'name' => 'Objective',
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

    private function objectiveStep(ContentArticle $article): ContentArticleStep
    {
        return ContentArticleStep::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->firstOrFail();
    }
}
