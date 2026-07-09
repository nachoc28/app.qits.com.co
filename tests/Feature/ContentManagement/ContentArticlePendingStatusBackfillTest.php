<?php

namespace Tests\Feature\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\Empresa;
use App\Models\User;
use App\Services\ContentManagement\ContentArticleInitialStatusBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentArticlePendingStatusBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_backfill_only_updates_processing_articles_without_started_flow(): void
    {
        $user = $this->createUser();
        $empresa = $this->createEmpresa();
        $unstarted = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ]);
        $withGeneration = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ]);
        $startedStage = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_STRATEGIC_REFINEMENT,
        ]);
        $pendingAlready = $this->createArticle($empresa, $user, [
            'main_status' => ContentArticle::MAIN_STATUS_PENDING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ]);

        $template = ContentMasterTemplate::create([
            'key' => ContentArticleStep::TYPE_OBJECTIVE,
            'name' => 'Objective',
            'is_active' => true,
        ]);
        $version = ContentMasterTemplateVersion::create([
            'content_master_template_id' => $template->id,
            'version_number' => 1,
            'template_body' => 'Body [ ].',
            'is_active' => true,
        ]);

        ContentArticleGeneration::create([
            'content_article_id' => $withGeneration->id,
            'content_master_template_version_id' => $version->id,
            'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
            'final_prompt_text' => 'Prompt previo',
            'generated_by' => $user->id,
            'generated_at' => now(),
        ]);

        $updated = app(ContentArticleInitialStatusBackfillService::class)->backfillUnstartedProcessingArticles();

        $this->assertSame(1, $updated);
        $this->assertSame(ContentArticle::MAIN_STATUS_PENDING, $unstarted->fresh()->main_status);
        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $withGeneration->fresh()->main_status);
        $this->assertSame(ContentArticle::MAIN_STATUS_PROCESSING, $startedStage->fresh()->main_status);
        $this->assertSame(ContentArticle::MAIN_STATUS_PENDING, $pendingAlready->fresh()->main_status);
    }

    private function createArticle(Empresa $empresa, User $user, array $attributes): ContentArticle
    {
        $import = ContentImport::create([
            'empresa_id' => $empresa->id,
            'import_name' => 'Import ' . uniqid('', true),
            'source_file_name' => 'source.xlsx',
            'imported_by' => $user->id,
            'imported_at' => now(),
        ]);

        return ContentArticle::create(array_merge([
            'content_import_id' => $import->id,
            'article_date' => now()->toDateString(),
            'topic' => 'Tema ' . uniqid('', true),
            'strategic_objective_general' => 'Objetivo general',
            'target_audience_general' => 'Publico general',
            'refined_objective' => null,
            'refined_target_audience' => null,
            'tone' => ContentArticle::TONE_TUTEO,
        ], $attributes));
    }

    private function createEmpresa(): Empresa
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        return Empresa::create([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => 'Empresa Test',
            'direccion' => 'Calle 123',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => 'empresa' . uniqid() . '@test.local',
            'active' => true,
        ]);
    }

    private function createUser(): User
    {
        return User::create([
            'name' => 'Usuario Test ' . uniqid(),
            'email' => 'user' . uniqid() . '@test.local',
            'password' => bcrypt('secret123'),
            'active' => true,
        ]);
    }
}
