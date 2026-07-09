<?php

namespace Tests\Feature\ContentManagement;

use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use Database\Seeders\ContentMasterTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ContentMasterTemplatesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_exactly_three_expected_templates_with_active_versions(): void
    {
        $this->seed(ContentMasterTemplatesSeeder::class);

        $expectedKeys = ['drafting', 'objective', 'video_instagram'];
        $templates = ContentMasterTemplate::query()
            ->with('activeVersion')
            ->orderBy('key')
            ->get();

        $this->assertSame($expectedKeys, $templates->pluck('key')->all());
        $this->assertCount(3, $templates);

        foreach ($templates as $template) {
            $this->assertTrue($template->is_active);
            $this->assertNotNull($template->activeVersion);
            $this->assertSame(1, (int) $template->activeVersion->version_number);
            $this->assertTrue($template->activeVersion->is_active);
            $this->assertSame(
                $this->sourceTemplateBodyForKey($template->key),
                $template->activeVersion->template_body
            );
        }
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_templates_or_versions(): void
    {
        $this->seed(ContentMasterTemplatesSeeder::class);
        $this->seed(ContentMasterTemplatesSeeder::class);

        $this->assertDatabaseCount('content_master_templates', 3);
        $this->assertDatabaseCount('content_master_template_versions', 3);

        $template = ContentMasterTemplate::query()
            ->where('key', 'drafting')
            ->with('versions')
            ->firstOrFail();

        $this->assertCount(1, $template->versions);
        $this->assertSame(1, (int) $template->versions->first()->version_number);
        $this->assertTrue((bool) $template->versions->first()->is_active);
    }

    public function test_seeder_fails_if_initial_version_exists_with_different_body(): void
    {
        $template = ContentMasterTemplate::query()->create([
            'key' => 'objective',
            'name' => 'Objective',
            'is_active' => true,
        ]);

        ContentMasterTemplateVersion::query()->create([
            'content_master_template_id' => $template->id,
            'version_number' => 1,
            'template_body' => 'Contenido distinto',
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different content');

        $this->seed(ContentMasterTemplatesSeeder::class);
    }

    private function sourceTemplateBodyForKey(string $key): string
    {
        $filenameByKey = [
            'objective' => '1_GENERACION OBJETIVO ARTICULO.txt',
            'drafting' => '2_REDACCION ARTICULO.txt',
            'video_instagram' => '3_GUION VIDEOS E INSTAGRAM.txt',
        ];

        return (string) file_get_contents(
            database_path('seeders/data/content-management/' . $filenameByKey[$key])
        );
    }
}
