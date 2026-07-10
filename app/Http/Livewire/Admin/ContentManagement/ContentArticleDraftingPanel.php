<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentDraftingPromptService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentArticleDraftingPanel extends Component
{
    protected $listeners = [
        'contentObjectiveUpdated' => 'refreshFromObjective',
    ];

    /** @var int */
    public $articleId;

    /** @var int|null */
    public $selectedGenerationId = null;

    public function refreshFromObjective(int $articleId): void
    {
        if ((int) $articleId !== (int) $this->articleId) {
            return;
        }

        $this->resetErrorBag('drafting');
    }

    public function mount(int $articleId, ContentAccessService $accessService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        $this->articleId = $articleId;
        $article = $this->resolveArticle($accessService);

        $this->selectedGenerationId = optional(
            $article->generations
                ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
                ->sortByDesc('id')
                ->first()
        )->id;
    }

    public function generatePrompt(
        ContentAccessService $accessService,
        ContentDraftingPromptService $promptService
    ): void {
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        $wasRegeneration = $article->generations()
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->exists();

        try {
            $generation = $promptService->generate($article, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'drafting' => $e->getMessage(),
            ]);
        }

        $this->selectedGenerationId = $generation->id;
        $this->emit('contentDraftingUpdated', (int) $this->articleId);
        session()->flash('content_drafting_success', $wasRegeneration ? 'Prompt 2 regenerado.' : 'Prompt 2 generado.');
    }

    public function markDraftingReady(
        ContentAccessService $accessService,
        ContentDraftingPromptService $promptService
    ): void {
        $article = $this->resolveArticle($accessService);

        try {
            $promptService->markReady($article, auth()->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'drafting' => $e->getMessage(),
            ]);
        }

        $this->emit('contentDraftingUpdated', (int) $this->articleId);
        session()->flash('content_drafting_success', 'Paso 2 marcado como listo.');
    }

    public function viewGeneration(int $generationId, ContentAccessService $accessService): void
    {
        $article = $this->resolveArticle($accessService);

        $exists = $article->generations()
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->whereKey($generationId)
            ->exists();

        if (! $exists) {
            throw new HttpException(404, 'Drafting generation not found for this content article.');
        }

        $this->selectedGenerationId = $generationId;
    }

    public function render(
        ContentAccessService $accessService,
        ContentDraftingPromptService $promptService
    ) {
        $article = $this->resolveArticle($accessService);
        $draftingStep = $article->steps->firstWhere('step_type', ContentArticleStep::TYPE_DRAFTING);
        $generations = $article->generations
            ->where('step_type', ContentArticleStep::TYPE_DRAFTING)
            ->sortByDesc('generated_at')
            ->values();
        $selectedGeneration = $generations->firstWhere('id', $this->selectedGenerationId) ?: $generations->first();

        return view('livewire.admin.content-management.content-article-drafting-panel', [
            'article' => $article,
            'draftingStep' => $draftingStep,
            'generations' => $generations,
            'selectedGeneration' => $selectedGeneration,
            'availability' => $promptService->availability($article),
        ]);
    }

    private function resolveArticle(ContentAccessService $accessService): ContentArticle
    {
        /** @var User $user */
        $user = auth()->user();

        $article = ContentArticle::query()
            ->with([
                'contentImport.empresa.seoProperty',
                'steps.readyBy',
                'generations.templateVersion.masterTemplate',
                'generations.generatedBy',
            ])
            ->findOrFail((int) $this->articleId);

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $article;
    }
}
