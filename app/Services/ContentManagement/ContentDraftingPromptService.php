<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentMasterTemplateVersion;
use App\Models\EmpresaSeoProperty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentDraftingPromptService
{
    /**
     * @return array{allowed: bool, message: string|null, site_url: string|null}
     */
    public function availability(ContentArticle $article): array
    {
        $article->loadMissing('contentImport.empresa.seoProperty', 'steps');

        $objectiveStep = $this->resolveStep($article, ContentArticleStep::TYPE_OBJECTIVE);

        if ($objectiveStep->step_status !== ContentArticleStep::STATUS_READY) {
            return [
                'allowed' => false,
                'message' => 'El paso objective debe estar listo antes de generar Prompt 2.',
                'site_url' => null,
            ];
        }

        if (trim((string) $article->refined_objective) === '' || trim((string) $article->refined_target_audience) === '') {
            return [
                'allowed' => false,
                'message' => 'Debes completar objetivo refinado y publico objetivo refinado antes de generar Prompt 2.',
                'site_url' => null,
            ];
        }

        $siteUrl = $this->extractSiteUrl($article);

        if ($siteUrl === null) {
            return [
                'allowed' => false,
                'message' => 'La empresa no tiene site_url configurado en EmpresaSeoProperty. Prompt 2 no puede generarse.',
                'site_url' => null,
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
            'site_url' => $siteUrl,
        ];
    }

    public function buildPrompt(ContentArticle $article): string
    {
        $templateVersion = $this->resolveActiveTemplateVersion();

        return $this->buildPromptFromTemplateVersion($article, $templateVersion);
    }

    public function generate(ContentArticle $article, User $user): ContentArticleGeneration
    {
        $article->loadMissing('steps', 'contentImport.empresa.seoProperty');
        $templateVersion = $this->resolveActiveTemplateVersion();
        $prompt = $this->buildPromptFromTemplateVersion($article, $templateVersion);

        return DB::transaction(function () use ($article, $user, $templateVersion, $prompt): ContentArticleGeneration {
            $draftingStep = $this->resolveStep($article, ContentArticleStep::TYPE_DRAFTING);
            $hasPreviousDraftingGeneration = ContentArticleGeneration::query()
                ->where('content_article_id', $article->id)
                ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
                ->exists();

            $generation = ContentArticleGeneration::query()->create([
                'content_article_id' => $article->id,
                'content_master_template_version_id' => $templateVersion->id,
                'step_type' => ContentArticleStep::TYPE_DRAFTING,
                'final_prompt_text' => $prompt,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ]);

            if (! $hasPreviousDraftingGeneration) {
                $article->forceFill([
                    'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                    'operational_stage' => ContentArticle::STAGE_DRAFTING,
                ])->save();

                $draftingStep->forceFill([
                    'step_status' => ContentArticleStep::STATUS_IN_PROGRESS,
                    'ready_at' => null,
                    'ready_by' => null,
                ])->save();
            }

            return $generation;
        });
    }

    public function markReady(ContentArticle $article, User $user): ContentArticleStep
    {
        $article->loadMissing('steps');

        $draftingStep = $this->resolveStep($article, ContentArticleStep::TYPE_DRAFTING);
        $hasGeneration = ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->exists();

        if (! $hasGeneration) {
            throw new RuntimeException('Debes generar al menos un Prompt 2 antes de marcar el paso drafting como listo.');
        }

        return DB::transaction(function () use ($article, $user, $draftingStep): ContentArticleStep {
            $draftingStep->forceFill([
                'step_status' => ContentArticleStep::STATUS_READY,
                'ready_by' => $user->id,
                'ready_at' => now(),
            ])->save();

            $article->forceFill([
                'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                'operational_stage' => ContentArticle::STAGE_VIDEO_INSTAGRAM,
            ])->save();

            return $draftingStep->fresh();
        });
    }

    public function resolveActiveTemplateVersion(): ContentMasterTemplateVersion
    {
        $version = ContentMasterTemplateVersion::query()
            ->with('masterTemplate')
            ->where('is_active', true)
            ->whereHas('masterTemplate', function ($query): void {
                $query->where('key', ContentArticleStep::TYPE_DRAFTING)
                    ->where('is_active', true);
            })
            ->first();

        if (! $version instanceof ContentMasterTemplateVersion) {
            throw new RuntimeException('Active master template version for drafting step is not available.');
        }

        return $version;
    }

    private function buildPromptFromTemplateVersion(
        ContentArticle $article,
        ContentMasterTemplateVersion $templateVersion
    ): string {
        $availability = $this->availability($article);

        if (! $availability['allowed'] || ! is_string($availability['site_url'])) {
            throw new RuntimeException((string) ($availability['message'] ?? 'Drafting step is not ready to generate.'));
        }

        $prompt = $templateVersion->template_body;
        $prompt = $this->replaceLine($prompt, 'URL del sitio web:', $availability['site_url']);
        $prompt = $this->replaceLineByFragment($prompt, 'Tema del art', (string) $article->topic);
        $prompt = $this->replaceLineByFragment($prompt, 'Objetivo del art', (string) $article->refined_objective);
        $prompt = $this->replaceLineByFragment($prompt, 'blico objetivo del art', (string) $article->refined_target_audience);
        $prompt = $this->replaceLine($prompt, 'Tono de voz:', $this->normalizeToneLabel((string) $article->tone));

        return $prompt;
    }

    private function replaceLine(string $templateBody, string $linePrefix, string $value): string
    {
        $lines = preg_split("/(\r\n|\n|\r)/", $templateBody);

        if (! is_array($lines)) {
            return $templateBody;
        }

        foreach ($lines as $index => $line) {
            if (strpos(trim($line), $linePrefix) === 0) {
                $lines[$index] = $linePrefix . ' ' . $value;
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function replaceLineByFragment(string $templateBody, string $fragment, string $value): string
    {
        $lines = preg_split("/(\r\n|\n|\r)/", $templateBody);

        if (! is_array($lines)) {
            return $templateBody;
        }

        foreach ($lines as $index => $line) {
            if (strpos($line, $fragment) !== false) {
                $label = strstr($line, ':', true);

                if ($label !== false) {
                    $lines[$index] = trim($label) . ': ' . $value;
                }
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function resolveStep(ContentArticle $article, string $stepType): ContentArticleStep
    {
        $step = $article->relationLoaded('steps')
            ? $article->steps->firstWhere('step_type', $stepType)
            : $article->steps()->where('step_type', $stepType)->first();

        if (! $step instanceof ContentArticleStep) {
            throw new RuntimeException(sprintf('Step [%s] is missing for the content article.', $stepType));
        }

        return $step;
    }

    private function extractSiteUrl(ContentArticle $article): ?string
    {
        $empresa = optional($article->contentImport)->empresa;
        $property = $empresa ? $empresa->seoProperty : null;

        if (! $property instanceof EmpresaSeoProperty) {
            return null;
        }

        $siteUrl = trim((string) $property->site_url);

        return $siteUrl === '' ? null : $siteUrl;
    }

    private function normalizeToneLabel(string $tone): string
    {
        return $tone === ContentArticle::TONE_USTEO ? 'Usteo' : 'Tuteo';
    }
}
