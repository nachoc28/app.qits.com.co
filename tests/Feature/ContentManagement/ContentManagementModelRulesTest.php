<?php

namespace Tests\Feature\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ContentManagementModelRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_prevents_second_active_version_for_same_template(): void
    {
        $template = ContentMasterTemplate::create([
            'key' => 'template-main',
            'name' => 'Template Main',
            'is_active' => true,
        ]);

        ContentMasterTemplateVersion::create([
            'content_master_template_id' => $template->id,
            'version_number' => 1,
            'template_body' => 'Body v1',
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only one active template version is allowed per master template.');

        ContentMasterTemplateVersion::create([
            'content_master_template_id' => $template->id,
            'version_number' => 2,
            'template_body' => 'Body v2',
            'is_active' => true,
        ]);
    }

    public function test_allows_active_versions_for_different_templates(): void
    {
        $templateA = ContentMasterTemplate::create([
            'key' => 'template-a',
            'name' => 'Template A',
            'is_active' => true,
        ]);

        $templateB = ContentMasterTemplate::create([
            'key' => 'template-b',
            'name' => 'Template B',
            'is_active' => true,
        ]);

        $versionA = ContentMasterTemplateVersion::create([
            'content_master_template_id' => $templateA->id,
            'version_number' => 1,
            'template_body' => 'Body A',
            'is_active' => true,
        ]);

        $versionB = ContentMasterTemplateVersion::create([
            'content_master_template_id' => $templateB->id,
            'version_number' => 1,
            'template_body' => 'Body B',
            'is_active' => true,
        ]);

        $this->assertTrue($versionA->is_active);
        $this->assertTrue($versionB->is_active);
    }

    public function test_prevents_objective_step_ready_without_refined_fields(): void
    {
        $article = $this->createArticle([
            'refined_objective' => null,
            'refined_target_audience' => null,
        ]);

        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Objective step cannot be marked ready without refined objective and refined target audience.'
        );

        ContentArticleStep::create([
            'content_article_id' => $article->id,
            'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now(),
        ]);
    }

    public function test_allows_objective_step_ready_with_refined_fields_complete(): void
    {
        $article = $this->createArticle([
            'refined_objective' => 'Refined objective',
            'refined_target_audience' => 'Refined audience',
        ]);

        $user = $this->createUser();

        $step = ContentArticleStep::create([
            'content_article_id' => $article->id,
            'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now(),
        ]);

        $this->assertSame(ContentArticleStep::STATUS_READY, $step->step_status);
        $this->assertSame($user->id, $step->ready_by);
    }

    private function createArticle(array $overrides = []): ContentArticle
    {
        $import = $this->createImport();

        return ContentArticle::create(array_merge([
            'content_import_id' => $import->id,
            'article_date' => now()->toDateString(),
            'topic' => 'Test topic',
            'strategic_objective_general' => 'General objective',
            'target_audience_general' => 'General audience',
            'refined_objective' => 'Refined objective',
            'refined_target_audience' => 'Refined audience',
            'tone' => ContentArticle::TONE_TUTEO,
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ], $overrides));
    }

    private function createImport(): ContentImport
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();

        return ContentImport::create([
            'empresa_id' => $empresa->id,
            'import_name' => 'Import ' . uniqid('', true),
            'source_file_name' => 'source.xlsx',
            'imported_by' => $user->id,
            'imported_at' => now(),
        ]);
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
