<?php

namespace Tests\Feature\ContentManagement;

use App\Http\Livewire\Admin\ContentManagement\ContentArticleFinalFilePanel;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class ContentArticleFinalFilePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('filesystems.disks.tmp-for-tests', [
            'driver' => 'local',
            'root' => storage_path('app/tmp-for-tests'),
        ]);
        config()->set('content_management.final_files.disk', 'local');
        config()->set('content_management.final_files.max_file_kb', 10240);
        config()->set('livewire.temporary_file_upload.disk', 'tmp-for-tests');
    }

    public function test_blocks_upload_if_video_instagram_is_not_ready(): void
    {
        $empresa = $this->createEmpresa('Empresa Video Pending');
        $user = $this->createUser('Administrador');
        $article = $this->createArticle($empresa, $user, [
            'operational_stage' => ContentArticle::STAGE_FINAL_FILE,
        ]);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedPdf())
            ->call('uploadFinalFile')
            ->assertHasErrors(['uploadFile']);

        $this->assertDatabaseCount('content_article_files', 0);
    }

    public function test_accepts_valid_docx_upload(): void
    {
        $empresa = $this->createEmpresa('Empresa DOCX');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedDocx())
            ->call('uploadFinalFile')
            ->assertHasNoErrors();

        $file = ContentArticleFile::query()->firstOrFail();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $file->mime_type);
        $this->assertStringEndsWith('.docx', $file->file_name);
        Storage::disk('local')->assertExists($file->file_path);
    }

    public function test_accepts_valid_pdf_upload(): void
    {
        $empresa = $this->createEmpresa('Empresa PDF');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedPdf())
            ->call('uploadFinalFile')
            ->assertHasNoErrors();

        $file = ContentArticleFile::query()->firstOrFail();
        $this->assertSame('application/pdf', $file->mime_type);
        $this->assertStringEndsWith('.pdf', $file->file_name);
        Storage::disk('local')->assertExists($file->file_path);
    }

    public function test_rejects_not_allowed_extension(): void
    {
        $empresa = $this->createEmpresa('Empresa TXT');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedText())
            ->call('uploadFinalFile')
            ->assertHasErrors(['uploadFile']);

        $this->assertDatabaseCount('content_article_files', 0);
    }

    public function test_rejects_inconsistent_mime(): void
    {
        $empresa = $this->createEmpresa('Empresa MIME');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeInvalidPdfUpload())
            ->call('uploadFinalFile')
            ->assertHasErrors(['uploadFile']);

        $this->assertDatabaseCount('content_article_files', 0);
    }

    public function test_creates_version_one_and_second_upload_creates_version_two(): void
    {
        $empresa = $this->createEmpresa('Empresa Versiones');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedPdf('articulo-v1.pdf'))
            ->call('uploadFinalFile')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(ContentArticleFinalFilePanel::class, ['articleId' => $article->id])
            ->set('uploadFile', $this->makeUploadedDocx('articulo-v2.docx'))
            ->call('uploadFinalFile')
            ->assertHasNoErrors();

        $files = ContentArticleFile::query()
            ->where('content_article_id', $article->id)
            ->orderBy('version_number')
            ->get();

        $this->assertCount(2, $files);
        $this->assertSame(1, $files[0]->version_number);
        $this->assertSame(2, $files[1]->version_number);
        $this->assertNotSame($files[0]->file_path, $files[1]->file_path);
        Storage::disk('local')->assertExists($files[0]->file_path);
        Storage::disk('local')->assertExists($files[1]->file_path);
    }

    public function test_preserves_history_and_does_not_expose_file_path_in_ui(): void
    {
        $empresa = $this->createEmpresa('Empresa UI');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        /** @var ContentFinalFileService $service */
        $service = app(ContentFinalFileService::class);
        $service->upload($article, $this->makeUploadedPdf('historial-1.pdf'), $user);
        $service->upload($article->fresh(), $this->makeUploadedDocx('historial-2.docx'), $user);

        $files = ContentArticleFile::query()
            ->where('content_article_id', $article->id)
            ->orderBy('version_number')
            ->get();

        $response = $this->actingAs($user)
            ->get(route('admin.content-management.articles.show', $article->fresh()));

        $response->assertOk();
        $response->assertSee('Version 2');
        $response->assertSee('historial-2.docx');
        $response->assertSee('historial-1.pdf');
        $response->assertDontSee($files[0]->file_path);
        $response->assertDontSee($files[1]->file_path);
    }

    public function test_user_without_access_cannot_upload(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $visibleArticle = $this->createReadyForFinalFileArticle($empresaVisible, $user);
        $hiddenArticle = $this->createReadyForFinalFileArticle($empresaOculta, $this->createUser('Administrador'));

        $this->actingAs($user);

        /** @var ContentArticleFinalFilePanel $component */
        $component = app(ContentArticleFinalFilePanel::class);
        $component->mount($visibleArticle->id, app(ContentAccessService::class));
        $component->articleId = $hiddenArticle->id;
        $component->uploadFile = $this->makeUploadedPdf();

        try {
            $component->uploadFinalFile(
                app(ContentAccessService::class),
                app(ContentFinalFileService::class)
            );

            $this->fail('Expected forbidden final file upload when tampering articleId.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_user_without_access_cannot_download(): void
    {
        $empresaVisible = $this->createEmpresa('Empresa Visible');
        $empresaOculta = $this->createEmpresa('Empresa Oculta');
        $user = $this->createUser('Cliente', $empresaVisible);
        $hiddenOwner = $this->createUser('Administrador');
        $hiddenArticle = $this->createReadyForFinalFileArticle($empresaOculta, $hiddenOwner);

        /** @var ContentFinalFileService $service */
        $service = app(ContentFinalFileService::class);
        $file = $service->upload($hiddenArticle, $this->makeUploadedPdf('oculto.pdf'), $hiddenOwner);

        $this->actingAs($user)
            ->get(route('admin.content-management.articles.files.download', [
                'article' => $hiddenArticle,
                'file' => $file,
            ]))
            ->assertForbidden();
    }

    public function test_authorized_download_works_and_does_not_expose_physical_path(): void
    {
        $empresa = $this->createEmpresa('Empresa Download');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        /** @var ContentFinalFileService $service */
        $service = app(ContentFinalFileService::class);
        $file = $service->upload($article, $this->makeUploadedPdf('descargable.pdf'), $user);

        $response = $this->actingAs($user)
            ->get(route('admin.content-management.articles.files.download', [
                'article' => $article,
                'file' => $file,
            ]));

        $response->assertOk();
        $response->assertDownload('descargable.pdf');
        $this->assertStringContainsString('%PDF-', $response->streamedContent());
        $this->assertStringNotContainsString($file->file_path, $response->streamedContent());
    }

    public function test_cleans_stored_file_when_persistence_fails(): void
    {
        $empresa = $this->createEmpresa('Empresa Cleanup');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);
        $upload = $this->makeUploadedPdf('cleanup.pdf');

        $service = new class extends ContentFinalFileService {
            protected function createContentArticleFile(array $attributes): ContentArticleFile
            {
                throw new RuntimeException('Forced persistence failure.');
            }
        };

        try {
            $service->upload($article, $upload, $user);
            $this->fail('Expected forced persistence failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Forced persistence failure.', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles('content-management/final-files/article_' . $article->id));
        $this->assertDatabaseCount('content_article_files', 0);
    }

    public function test_upload_moves_operational_stage_to_completed_and_main_status_to_unpublished(): void
    {
        $empresa = $this->createEmpresa('Empresa Estado Final');
        $user = $this->createUser('Administrador');
        $article = $this->createReadyForFinalFileArticle($empresa, $user);

        /** @var ContentFinalFileService $service */
        $service = app(ContentFinalFileService::class);
        $service->upload($article, $this->makeUploadedPdf(), $user);

        $article->refresh();

        $this->assertSame(ContentArticle::STAGE_COMPLETED, $article->operational_stage);
        $this->assertSame(ContentArticle::MAIN_STATUS_UNPUBLISHED, $article->main_status);
        $this->assertNull($article->delivered_at);
        $this->assertNull($article->published_at);
    }

    private function makeUploadedPdf(string $name = 'articulo-final.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        );
    }

    private function makeInvalidPdfUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('invalido.pdf', 'plain text');
    }

    private function makeUploadedDocx(string $name = 'articulo-final.docx'): UploadedFile
    {
        $basePath = tempnam(sys_get_temp_dir(), 'content-docx-');
        $docxPath = $basePath . '.docx';
        @unlink($basePath);

        $zip = new ZipArchive();
        $zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Documento final</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        $contents = file_get_contents($docxPath);
        @unlink($docxPath);

        return UploadedFile::fake()->createWithContent($name, $contents ?: '');
    }

    private function makeUploadedText(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('archivo.txt', 'texto plano');
    }

    private function createReadyForFinalFileArticle(Empresa $empresa, User $user): ContentArticle
    {
        $article = $this->createArticle($empresa, $user, [
            'refined_objective' => 'Objetivo refinado',
            'refined_target_audience' => 'Publico refinado',
            'operational_stage' => ContentArticle::STAGE_FINAL_FILE,
        ]);

        $this->markObjectiveReady($article, $user);
        $this->markDraftingReady($article, $user);
        $this->markVideoInstagramReady($article, $user);

        return $article->fresh();
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
