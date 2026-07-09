<?php

namespace App\Services\ContentManagement;

use App\Exceptions\ContentManagement\MissingActiveTemplateVersionException;
use App\Models\ContentArticle;
use App\Models\ContentArticleGeneration;
use App\Models\ContentArticleStep;
use App\Models\ContentMasterTemplate;
use App\Models\ContentMasterTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentObjectivePromptService
{
    public function buildPrompt(ContentArticle $article): string
    {
        $templateVersion = $this->resolveActiveTemplateVersion();
        
        return $this->buildPromptFromTemplateVersion($article, $templateVersion);
    }

    private function buildPromptFromTemplateVersion(
        ContentArticle $article,
        ContentMasterTemplateVersion $templateVersion
    ): string {
        $article->loadMissing('contentImport.empresa');

        $templateBody = str_replace('[ ]', (string) $article->topic, $templateVersion->template_body);
        $contextLines = [
            'Contexto disponible del articulo:',
        ];

        if (($empresaNombre = trim((string) optional(optional($article->contentImport)->empresa)->nombre)) !== '') {
            $contextLines[] = 'Empresa: ' . $empresaNombre;
        }

        $contextLines[] = 'Tema: ' . trim((string) $article->topic);
        $contextLines[] = 'Objetivo estrategico general: ' . trim((string) $article->strategic_objective_general);
        $contextLines[] = 'Publico objetivo general: ' . trim((string) $article->target_audience_general);

        return trim($templateBody) . PHP_EOL . PHP_EOL . implode(PHP_EOL, $contextLines);
    }

    public function generate(ContentArticle $article, User $user): ContentArticleGeneration
    {
        $article->loadMissing('steps', 'contentImport.empresa');
        $templateVersion = $this->resolveActiveTemplateVersion();
        $prompt = $this->buildPromptFromTemplateVersion($article, $templateVersion);

        return DB::transaction(function () use ($article, $user, $templateVersion, $prompt): ContentArticleGeneration {
            $objectiveStep = $this->resolveObjectiveStep($article);
            $hasPreviousObjectiveGeneration = ContentArticleGeneration::query()
                ->where('content_article_id', $article->id)
                ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
                ->exists();

            $generation = ContentArticleGeneration::query()->create([
                'content_article_id' => $article->id,
                'content_master_template_version_id' => $templateVersion->id,
                'step_type' => ContentArticleStep::TYPE_OBJECTIVE,
                'final_prompt_text' => $prompt,
                'generated_by' => $user->id,
                'generated_at' => now(),
            ]);

            if (! $hasPreviousObjectiveGeneration) {
                $article->forceFill([
                    'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                    'operational_stage' => ContentArticle::STAGE_STRATEGIC_REFINEMENT,
                ])->save();

                $objectiveStep->forceFill([
                    'step_status' => ContentArticleStep::STATUS_IN_PROGRESS,
                    'ready_at' => null,
                    'ready_by' => null,
                ])->save();
            }

            return $generation;
        });
    }

    /**
     * @param  array<string, string|null>  $attributes
     */
    public function saveRefinedFields(ContentArticle $article, array $attributes): ContentArticle
    {
        $article->forceFill([
            'refined_objective' => $this->nullableTrim($attributes['refined_objective'] ?? null),
            'refined_target_audience' => $this->nullableTrim($attributes['refined_target_audience'] ?? null),
        ])->save();

        return $article->fresh();
    }

    public function markReady(ContentArticle $article, User $user): ContentArticleStep
    {
        $article->loadMissing('steps');

        $step = $this->resolveObjectiveStep($article);
        $step->forceFill([
            'step_status' => ContentArticleStep::STATUS_READY,
            'ready_by' => $user->id,
            'ready_at' => now(),
        ])->save();

        return $step->fresh();
    }

    public function resolveActiveTemplateVersion(): ContentMasterTemplateVersion
    {
        $version = ContentMasterTemplateVersion::query()
            ->with('masterTemplate')
            ->where('is_active', true)
            ->whereHas('masterTemplate', function ($query): void {
                $query->where('key', ContentArticleStep::TYPE_OBJECTIVE)
                    ->where('is_active', true);
            })
            ->first();

        if (! $version instanceof ContentMasterTemplateVersion) {
            throw new MissingActiveTemplateVersionException(
                ContentArticleStep::TYPE_OBJECTIVE,
                $this->templateAvailabilityContext(ContentArticleStep::TYPE_OBJECTIVE)
            );
        }

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function templateAvailabilityContext(string $templateKey): array
    {
        $template = ContentMasterTemplate::query()
            ->where('key', $templateKey)
            ->first();

        if (! $template instanceof ContentMasterTemplate) {
            return [
                'template_exists' => false,
                'template_is_active' => null,
                'versions_count' => 0,
                'active_versions_count' => 0,
            ];
        }

        return [
            'template_exists' => true,
            'template_id' => $template->id,
            'template_is_active' => (bool) $template->is_active,
            'versions_count' => ContentMasterTemplateVersion::query()
                ->where('content_master_template_id', $template->id)
                ->count(),
            'active_versions_count' => ContentMasterTemplateVersion::query()
                ->where('content_master_template_id', $template->id)
                ->where('is_active', true)
                ->count(),
        ];
    }

    private function resolveObjectiveStep(ContentArticle $article): ContentArticleStep
    {
        $step = $article->relationLoaded('steps')
            ? $article->steps->firstWhere('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            : $article->steps()->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)->first();

        if (! $step instanceof ContentArticleStep) {
            throw new RuntimeException('Objective step is missing for the content article.');
        }

        return $step;
    }

    private function nullableTrim(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
