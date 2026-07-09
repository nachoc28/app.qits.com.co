<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentMasterTemplateVersion;
use App\Models\User;
use App\Support\ContentManagementLabels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentVideoInstagramPromptService
{
    private const ATTACHMENT_INSTRUCTION = 'Adjunta en ChatGPT el documento final del articulo en formato Word o PDF antes de ejecutar este prompt.';

    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function availability(ContentArticle $article): array
    {
        $article->loadMissing('steps');

        $draftingStep = $this->resolveStep($article, ContentArticleStep::TYPE_DRAFTING);

        if ($draftingStep->step_status !== ContentArticleStep::STATUS_READY) {
            return [
                'allowed' => false,
                'message' => 'El paso ' . ContentManagementLabels::stepType(ContentArticleStep::TYPE_DRAFTING) . ' debe estar listo antes de generar Prompt 3.',
            ];
        }

        $hasDraftingGeneration = ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->exists();

        if (! $hasDraftingGeneration) {
            return [
                'allowed' => false,
                'message' => 'Debe existir al menos una generacion de ' . ContentManagementLabels::stepType(ContentArticleStep::TYPE_DRAFTING) . ' antes de generar Prompt 3.',
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
    }

    public function buildPrompt(ContentArticle $article): string
    {
        $templateVersion = $this->resolveActiveTemplateVersion();

        return $this->buildPromptFromTemplateVersion($article, $templateVersion);
    }

    public function generate(ContentArticle $article, User $user): ContentArticleGeneration
    {
        $article->loadMissing('steps');
        $templateVersion = $this->resolveActiveTemplateVersion();
        $prompt = $this->buildPromptFromTemplateVersion($article, $templateVersion);

        return DB::transaction(function () use ($article, $user, $templateVersion, $prompt): ContentArticleGeneration {
            $videoStep = $this->resolveStep($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
            $hasPreviousGeneration = ContentArticleGeneration::query()
                ->where('content_article_id', $article->id)
                ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
                ->exists();

            $generation = ContentArticleGeneration::query()->create([
                'content_article_id' => $article->id,
                'content_master_template_version_id' => $templateVersion->id,
                'step_type' => ContentArticleStep::TYPE_VIDEO_INSTAGRAM,
                'final_prompt_text' => $prompt,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ]);

            if (! $hasPreviousGeneration) {
                $article->forceFill([
                    'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                    'operational_stage' => ContentArticle::STAGE_VIDEO_INSTAGRAM,
                ])->save();

                $videoStep->forceFill([
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

        $videoStep = $this->resolveStep($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $hasGeneration = ContentArticleGeneration::query()
            ->where('content_article_id', $article->id)
            ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
            ->exists();

        if (! $hasGeneration) {
            throw new RuntimeException('Debes generar al menos un Prompt 3 antes de marcar el paso ' . ContentManagementLabels::stepType(ContentArticleStep::TYPE_VIDEO_INSTAGRAM) . ' como listo.');
        }

        return DB::transaction(function () use ($article, $user, $videoStep): ContentArticleStep {
            $videoStep->forceFill([
                'step_status' => ContentArticleStep::STATUS_READY,
                'ready_by' => $user->id,
                'ready_at' => now(),
            ])->save();

            $article->forceFill([
                'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                'operational_stage' => ContentArticle::STAGE_FINAL_FILE,
            ])->save();

            return $videoStep->fresh();
        });
    }

    public function resolveActiveTemplateVersion(): ContentMasterTemplateVersion
    {
        $version = ContentMasterTemplateVersion::query()
            ->with('masterTemplate')
            ->where('is_active', true)
            ->whereHas('masterTemplate', function ($query): void {
                $query->where('key', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
                    ->where('is_active', true);
            })
            ->first();

        if (! $version instanceof ContentMasterTemplateVersion) {
            throw new RuntimeException('La plantilla necesaria para Video e Instagram no está configurada.');
        }

        return $version;
    }

    public static function attachmentInstruction(): string
    {
        return self::ATTACHMENT_INSTRUCTION;
    }

    private function buildPromptFromTemplateVersion(
        ContentArticle $article,
        ContentMasterTemplateVersion $templateVersion
    ): string {
        $availability = $this->availability($article);

        if (! $availability['allowed']) {
            throw new RuntimeException((string) ($availability['message'] ?? 'El paso Video e Instagram no está listo para generar.'));
        }

        return implode(PHP_EOL . PHP_EOL, [
            self::ATTACHMENT_INSTRUCTION,
            'Contexto minimo disponible del articulo:',
            'Tema: ' . trim((string) $article->topic),
            'No se adjunta automaticamente el contenido final del articulo ni se simula su lectura.',
            trim($templateVersion->template_body),
        ]);
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
}
