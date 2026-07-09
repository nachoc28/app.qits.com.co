<?php

namespace Database\Seeders;

use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentMasterTemplatesSeeder extends Seeder
{
    private const INITIAL_VERSION_NUMBER = 1;

    /**
     * @var array<string, array{name:string,file:string}>
     */
    private const TEMPLATE_DEFINITIONS = [
        'objective' => [
            'name' => 'Objective',
            'file' => '1_GENERACION OBJETIVO ARTICULO.txt',
        ],
        'drafting' => [
            'name' => 'Drafting',
            'file' => '2_REDACCION ARTICULO.txt',
        ],
        'video_instagram' => [
            'name' => 'Video Instagram',
            'file' => '3_GUION VIDEOS E INSTAGRAM.txt',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::TEMPLATE_DEFINITIONS as $key => $definition) {
                $this->registerTemplate($key, $definition['name'], $definition['file']);
            }

            $this->validateRegisteredTemplates();
        });
    }

    private function registerTemplate(string $key, string $name, string $filename): void
    {
        $templateBody = $this->loadTemplateBody($filename);

        /** @var ContentMasterTemplate $template */
        $template = ContentMasterTemplate::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $name,
                'is_active' => true,
            ]
        );

        if ($template->name !== $name || ! $template->is_active) {
            $template->forceFill([
                'name' => $name,
                'is_active' => true,
            ])->save();
        }

        /** @var ContentMasterTemplateVersion|null $version */
        $version = ContentMasterTemplateVersion::query()
            ->where('content_master_template_id', $template->id)
            ->where('version_number', self::INITIAL_VERSION_NUMBER)
            ->first();

        if (! $version) {
            $this->guardAgainstAnotherActiveVersion($template);

            ContentMasterTemplateVersion::query()->create([
                'content_master_template_id' => $template->id,
                'version_number' => self::INITIAL_VERSION_NUMBER,
                'template_body' => $templateBody,
                'is_active' => true,
            ]);

            return;
        }

        if ($version->template_body !== $templateBody) {
            throw new RuntimeException(sprintf(
                'Template key [%s] already has version [%d] with different content. Historical versions are not overwritten silently.',
                $key,
                self::INITIAL_VERSION_NUMBER
            ));
        }

        if (! $version->is_active) {
            $this->guardAgainstAnotherActiveVersion($template, $version->id);

            $version->forceFill([
                'is_active' => true,
            ])->save();
        }
    }

    private function loadTemplateBody(string $filename): string
    {
        $path = database_path('seeders/data/content-management/' . $filename);
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read content master template source file: ' . $path);
        }

        return $contents;
    }

    private function guardAgainstAnotherActiveVersion(ContentMasterTemplate $template, ?int $ignoreVersionId = null): void
    {
        $query = ContentMasterTemplateVersion::query()
            ->where('content_master_template_id', $template->id)
            ->where('is_active', true);

        if ($ignoreVersionId !== null) {
            $query->where('id', '<>', $ignoreVersionId);
        }

        if ($query->exists()) {
            throw new RuntimeException(sprintf(
                'Template key [%s] already has another active version. Initial registration cannot change historical activation silently.',
                $template->key
            ));
        }
    }

    private function validateRegisteredTemplates(): void
    {
        $keys = array_keys(self::TEMPLATE_DEFINITIONS);

        $templates = ContentMasterTemplate::query()
            ->with('activeVersion')
            ->whereIn('key', $keys)
            ->get();

        if ($templates->count() !== count($keys)) {
            throw new RuntimeException('Content master template registration did not produce exactly the 3 expected keys.');
        }

        foreach ($templates as $template) {
            if (! $template->activeVersion instanceof ContentMasterTemplateVersion) {
                throw new RuntimeException(sprintf(
                    'Template key [%s] does not have an active version after registration.',
                    $template->key
                ));
            }

            $expectedBody = $this->loadTemplateBody(self::TEMPLATE_DEFINITIONS[$template->key]['file']);

            if ($template->activeVersion->template_body !== $expectedBody) {
                throw new RuntimeException(sprintf(
                    'Active version for template key [%s] does not match the approved source content.',
                    $template->key
                ));
            }
        }
    }
}
