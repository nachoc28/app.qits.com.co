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
        $this->emit('contentObjectiveUpdated', (int) $this->articleId);
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
        $this->emit('contentObjectiveUpdated', (int) $this->articleId);
        session()->flash('content_objective_success', 'Resultados guardados.');
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

        $this->emit('contentObjectiveUpdated', (int) $this->articleId);
        session()->flash('content_objective_success', 'Paso 1 marcado como listo.');
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
            'stepperSteps' => $this->buildStepperSteps($article),
        ]);
    }

    /**
     * @return array<int, array{label: string, target: string, status: string, theme: string}>
     */
    private function buildStepperSteps(ContentArticle $article): array
    {
        $objectiveStep = $this->stepByType($article, ContentArticleStep::TYPE_OBJECTIVE);
        $draftingStep = $this->stepByType($article, ContentArticleStep::TYPE_DRAFTING);
        $videoStep = $this->stepByType($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $objectiveStatus = $this->stepperStatusFromStep($objectiveStep, false);
        $draftingStatus = $this->stepperStatusFromStep($draftingStep, ! $this->stepIsReady($objectiveStep));
        $videoStatus = $this->stepperStatusFromStep($videoStep, ! $this->stepIsReady($draftingStep));
        $finalFileStatus = $this->finalFileStepperStatus($article, $videoStep);
        $releaseStatus = $this->releaseStepperStatus($article);

        return [
            [
                'label' => 'Objetivo y público',
                'target' => 'content-step-objective',
                'status' => $objectiveStatus,
                'theme' => $this->stepperTheme($objectiveStatus),
            ],
            [
                'label' => 'Redacción',
                'target' => 'content-step-drafting',
                'status' => $draftingStatus,
                'theme' => $this->stepperTheme($draftingStatus),
            ],
            [
                'label' => 'Video e Instagram',
                'target' => 'content-step-video-instagram',
                'status' => $videoStatus,
                'theme' => $this->stepperTheme($videoStatus),
            ],
            [
                'label' => 'Archivo final',
                'target' => 'content-step-final-file',
                'status' => $finalFileStatus,
                'theme' => $this->stepperTheme($finalFileStatus),
            ],
            [
                'label' => 'Entrega / Publicación',
                'target' => 'content-step-release',
                'status' => $releaseStatus,
                'theme' => $this->stepperTheme($releaseStatus),
            ],
        ];
    }

    private function stepByType(ContentArticle $article, string $stepType): ?ContentArticleStep
    {
        return $article->steps->firstWhere('step_type', $stepType);
    }

    private function stepIsReady(?ContentArticleStep $step): bool
    {
        return $step && $step->step_status === ContentArticleStep::STATUS_READY;
    }

    private function stepperStatusFromStep(?ContentArticleStep $step, bool $isBlocked): string
    {
        if ($isBlocked) {
            return 'Bloqueado';
        }

        if (! $step) {
            return 'Pendiente';
        }

        if ($step->step_status === ContentArticleStep::STATUS_READY) {
            return 'Listo';
        }

        if ($step->step_status === ContentArticleStep::STATUS_IN_PROGRESS) {
            return 'En proceso';
        }

        return 'Pendiente';
    }

    private function finalFileStepperStatus(ContentArticle $article, ?ContentArticleStep $videoStep): string
    {
        if (! $this->stepIsReady($videoStep)) {
            return 'Bloqueado';
        }

        if ($article->files->isNotEmpty()) {
            return 'Listo';
        }

        if ($article->operational_stage === ContentArticle::STAGE_FINAL_FILE) {
            return 'En proceso';
        }

        return 'Pendiente';
    }

    private function releaseStepperStatus(ContentArticle $article): string
    {
        if ($article->published_at || $article->delivered_at) {
            return 'Listo';
        }

        if ($article->files->isNotEmpty()) {
            return 'Pendiente';
        }

        return 'Bloqueado';
    }

    private function stepperTheme(string $status): string
    {
        if ($status === 'Listo') {
            return 'emerald';
        }

        if ($status === 'En proceso') {
            return 'blue';
        }

        if ($status === 'Bloqueado') {
            return 'slate';
        }

        return 'amber';
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
