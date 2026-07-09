<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Exceptions\ContentManagement\MissingActiveTemplateVersionException;
use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentObjectivePromptService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentArticleObjectiveDetail extends Component
{
    /** @var int */
    public $articleId;

    /** @var string */
    public $refinedObjective = '';

    /** @var string */
    public $refinedTargetAudience = '';

    /** @var int|null */
    public $selectedGenerationId = null;

    /** @var string|null */
    public $templateConfigurationMessage = null;

    public function mount(int $articleId, ContentAccessService $accessService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        $this->articleId = $articleId;
        $article = $this->resolveArticle($accessService);

        $this->refinedObjective = (string) ($article->refined_objective ?? '');
        $this->refinedTargetAudience = (string) ($article->refined_target_audience ?? '');
        $this->selectedGenerationId = optional(
            $article->generations
                ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
                ->sortByDesc('id')
                ->first()
        )->id;
    }

    public function generatePrompt(
        ContentAccessService $accessService,
        ContentObjectivePromptService $promptService
    ): void {
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        try {
            $generation = $promptService->generate($article, $user);
        } catch (MissingActiveTemplateVersionException $e) {
            Log::error('[CONTENT][PROMPT][OBJECTIVE_TEMPLATE_MISSING] Active template version is not available.', array_merge(
                $e->context(),
                [
                    'content_article_id' => $article->id,
                    'user_id' => $user->id,
                    'route' => optional(request()->route())->getName(),
                ]
            ));

            $this->templateConfigurationMessage = $e->userMessage();

            return;
        }

        $this->selectedGenerationId = $generation->id;
        $this->templateConfigurationMessage = null;
        session()->flash('content_objective_success', 'Prompt 1 generado correctamente.');
    }

    public function saveRefinedResults(
        ContentAccessService $accessService,
        ContentObjectivePromptService $promptService
    ): void {
        $this->validate([
            'refinedObjective' => ['nullable', 'string'],
            'refinedTargetAudience' => ['nullable', 'string'],
        ]);

        $article = $this->resolveArticle($accessService);
        $updated = $promptService->saveRefinedFields($article, [
            'refined_objective' => $this->refinedObjective,
            'refined_target_audience' => $this->refinedTargetAudience,
        ]);

        $this->refinedObjective = (string) ($updated->refined_objective ?? '');
        $this->refinedTargetAudience = (string) ($updated->refined_target_audience ?? '');
        session()->flash('content_objective_success', 'Resultados refinados guardados.');
    }

    public function markObjectiveReady(
        ContentAccessService $accessService,
        ContentObjectivePromptService $promptService
    ): void {
        $this->validate([
            'refinedObjective' => ['required', 'string'],
            'refinedTargetAudience' => ['required', 'string'],
        ], [
            'refinedObjective.required' => 'Debes completar el objetivo refinado antes de marcar el paso como listo.',
            'refinedTargetAudience.required' => 'Debes completar el publico objetivo refinado antes de marcar el paso como listo.',
        ]);

        $article = $this->resolveArticle($accessService);
        $article = $promptService->saveRefinedFields($article, [
            'refined_objective' => $this->refinedObjective,
            'refined_target_audience' => $this->refinedTargetAudience,
        ]);

        try {
            $promptService->markReady($article, auth()->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'refinedObjective' => $e->getMessage(),
            ]);
        }

        session()->flash('content_objective_success', 'Paso Objetivo y público marcado como listo.');
    }

    public function viewGeneration(int $generationId, ContentAccessService $accessService): void
    {
        $article = $this->resolveArticle($accessService);

        $exists = $article->generations()
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->whereKey($generationId)
            ->exists();

        if (! $exists) {
            throw new HttpException(404, 'Objective generation not found for this content article.');
        }

        $this->selectedGenerationId = $generationId;
    }

    public function render(ContentAccessService $accessService)
    {
        $article = $this->resolveArticle($accessService);
        $objectiveStep = $article->steps->firstWhere('step_type', ContentArticleStep::TYPE_OBJECTIVE);
        $generations = $article->generations
            ->where('step_type', ContentArticleStep::TYPE_OBJECTIVE)
            ->sortByDesc('generated_at')
            ->values();
        $selectedGeneration = $generations->firstWhere('id', $this->selectedGenerationId) ?: $generations->first();

        return view('livewire.admin.content-management.content-article-objective-detail', [
            'article' => $article,
            'objectiveStep' => $objectiveStep,
            'generations' => $generations,
            'selectedGeneration' => $selectedGeneration,
        ]);
    }

    private function resolveArticle(ContentAccessService $accessService): ContentArticle
    {
        /** @var User $user */
        $user = auth()->user();

        $article = ContentArticle::query()
            ->with([
                'contentImport.empresa',
                'steps.readyBy',
                'generations.templateVersion.masterTemplate',
                'generations.generatedBy',
                'files.uploadedBy',
                'deliveredBy',
                'publishedBy',
            ])
            ->findOrFail((int) $this->articleId);

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $article;
    }
}
