<?php

namespace Tests\Feature\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentImport;
use App\Models\Empresa;
use App\Models\User;
use App\Services\ContentManagement\ContentXlsxImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class ContentXlsxImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_imports_valid_xlsx_successfully(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico', 'Público objetivo'],
            ['2026-07-08', 'Tema Uno', 'Objetivo Uno', 'Publico Uno'],
            ['2026-07-09', 'Tema Dos', 'Objetivo Dos', 'Publico Dos'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
            'import_name' => 'Import valid',
        ]);

        $this->assertTrue($result['persisted']);
        $this->assertSame(2, $result['total_rows']);
        $this->assertSame(2, $result['valid_rows']);
        $this->assertSame(2, $result['created']);
        $this->assertCount(0, $result['errors']);
        $this->assertDatabaseCount('content_imports', 1);
        $this->assertDatabaseCount('content_articles', 2);
        $this->assertDatabaseCount('content_article_steps', 6);
    }

    public function test_reports_missing_header(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico'],
            ['2026-07-08', 'Tema Uno', 'Objetivo Uno'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame('header', $result['errors'][0]['field']);
        $this->assertStringContainsString('Faltan columnas requeridas', $result['errors'][0]['message']);
        $this->assertDatabaseCount('content_imports', 0);
        $this->assertDatabaseCount('content_articles', 0);
    }

    public function test_reports_invalid_date(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico', 'Público objetivo'],
            ['fecha-mala', 'Tema Uno', 'Objetivo Uno', 'Publico Uno'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame(1, $result['total_rows']);
        $this->assertSame(0, $result['valid_rows']);
        $this->assertSame('fecha', $result['errors'][0]['field']);
        $this->assertDatabaseCount('content_articles', 0);
    }

    public function test_reports_required_empty_field(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico', 'Público objetivo'],
            ['2026-07-08', '', 'Objetivo Uno', 'Publico Uno'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame('tema_del_articulo', $result['errors'][0]['field']);
        $this->assertDatabaseCount('content_articles', 0);
    }

    public function test_does_not_persist_anything_when_one_row_is_invalid(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico', 'Público objetivo'],
            ['2026-07-08', 'Tema Uno', 'Objetivo Uno', 'Publico Uno'],
            ['2026-07-09', '', 'Objetivo Dos', 'Publico Dos'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame(2, $result['total_rows']);
        $this->assertSame(1, $result['valid_rows']);
        $this->assertDatabaseCount('content_imports', 0);
        $this->assertDatabaseCount('content_articles', 0);
        $this->assertDatabaseCount('content_article_steps', 0);
    }

    public function test_detects_duplicate_against_existing_records(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $existingImport = ContentImport::create([
            'empresa_id' => $empresa->id,
            'import_name' => 'Existing',
            'source_file_name' => 'existing.xlsx',
            'imported_by' => $user->id,
            'imported_at' => now(),
        ]);

        ContentArticle::create([
            'content_import_id' => $existingImport->id,
            'article_date' => '2026-07-08',
            'topic' => 'Tema Duplicado',
            'strategic_objective_general' => 'Objetivo',
            'target_audience_general' => 'Publico',
            'refined_objective' => null,
            'refined_target_audience' => null,
            'tone' => ContentArticle::TONE_TUTEO,
            'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
            'operational_stage' => ContentArticle::STAGE_PENDING,
        ]);

        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artículo', 'Objetivo estratégico', 'Público objetivo'],
            ['2026-07-08', '  tema   duplicado ', 'Objetivo Uno', 'Publico Uno'],
        ]);

        $result = app(ContentXlsxImportService::class)->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame('tema_del_articulo', $result['errors'][0]['field']);
        $this->assertStringContainsString('Ya existe un art', $result['errors'][0]['message']);
        $this->assertDatabaseCount('content_imports', 1);
        $this->assertDatabaseCount('content_articles', 1);
    }

    public function test_rolls_back_all_records_when_persistence_fails(): void
    {
        $empresa = $this->createEmpresa();
        $user = $this->createUser();
        $file = $this->makeUploadedXlsx([
            ['Fecha', 'Tema del artÃ­culo', 'Objetivo estratÃ©gico', 'PÃºblico objetivo'],
            ['2026-07-08', 'Tema Uno', 'Objetivo Uno', 'Publico Uno'],
        ]);

        $service = new class extends ContentXlsxImportService {
            /** @var bool */
            private $hasFailed = false;

            protected function createContentArticleStep(array $attributes): \App\Models\ContentArticleStep
            {
                if (! $this->hasFailed) {
                    $this->hasFailed = true;

                    throw new RuntimeException('Forced failure for rollback test.');
                }

                return parent::createContentArticleStep($attributes);
            }
        };

        $result = $service->importUploadedFile($empresa, $user, $file, [
            'tone' => ContentArticle::TONE_TUTEO,
            'import_name' => 'Rollback import',
        ]);

        $this->assertFalse($result['persisted']);
        $this->assertSame(0, $result['created']);
        $this->assertDatabaseCount('content_imports', 0);
        $this->assertDatabaseCount('content_articles', 0);
        $this->assertDatabaseCount('content_article_steps', 0);
    }

    public function test_prunes_old_orphaned_temp_xlsx_file(): void
    {
        Storage::fake('local');

        $oldPath = 'tmp/content-management/imports/orphan-old.xlsx';
        Storage::disk('local')->put($oldPath, 'old-xlsx');
        touch(Storage::disk('local')->path($oldPath), now()->subHours(5)->getTimestamp());

        Artisan::call('content-management:prune-temp-imports', [
            '--older-than-minutes' => 180,
        ]);

        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_keeps_recent_temp_xlsx_file(): void
    {
        Storage::fake('local');

        $recentPath = 'tmp/content-management/imports/recent.xlsx';
        Storage::disk('local')->put($recentPath, 'recent-xlsx');
        touch(Storage::disk('local')->path($recentPath), now()->subMinutes(20)->getTimestamp());

        Artisan::call('content-management:prune-temp-imports', [
            '--older-than-minutes' => 180,
        ]);

        Storage::disk('local')->assertExists($recentPath);
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

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeUploadedXlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $basePath = tempnam(sys_get_temp_dir(), 'content-xlsx-');
        $xlsxPath = $basePath . '.xlsx';
        @unlink($basePath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        return new UploadedFile(
            $xlsxPath,
            'content-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
