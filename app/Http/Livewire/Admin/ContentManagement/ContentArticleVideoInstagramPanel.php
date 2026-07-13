<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentVideoInstagramPromptService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentArticleVideoInstagramPanel extends Component
{
    protected $listeners = [
        'contentDraftingUpdated' => 'refreshFromDrafting',
    ];

    /** @var int */
    public $articleId;

    /** @var int|null */
    public $selectedGenerationId = null;

    public function refreshFromDrafting(int $articleId): void
    {
        if ((int) $articleId !== (int) $this->articleId) {
            return;
        }

        $this->resetErrorBag('video_instagram');
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
                ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
                ->sortByDesc('id')
                ->first()
        )->id;
    }

    public function generatePrompt(
        ContentAccessService $accessService,
        ContentVideoInstagramPromptService $promptService
    ): void {
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        $wasRegeneration = $article->generations()
            ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
            ->exists();

        try {
            $generation = $promptService->generate($article, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'video_instagram' => $e->getMessage(),
            ]);
        }

        $this->selectedGenerationId = $generation->id;
        session()->flash('content_video_instagram_prompt_success', $wasRegeneration ? 'Prompt 3 regenerado.' : 'Prompt 3 generado.');
    }

    public function markVideoInstagramReady(
        ContentAccessService $accessService,
        ContentVideoInstagramPromptService $promptService
    ): void {
        $article = $this->resolveArticle($accessService);

        try {
            $promptService->markReady($article, auth()->user());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'video_instagram' => $e->getMessage(),
            ]);
        }

        session()->flash('content_video_instagram_ready_success', 'Paso 3 marcado como listo.');
    }

    public function viewGeneration(int $generationId, ContentAccessService $accessService): void
    {
        $article = $this->resolveArticle($accessService);

        $exists = $article->generations()
            ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
            ->whereKey($generationId)
            ->exists();

        if (! $exists) {
            throw new HttpException(404, 'Video Instagram generation not found for this content article.');
        }

        $this->selectedGenerationId = $generationId;
    }

    public function render(
        ContentAccessService $accessService,
        ContentVideoInstagramPromptService $promptService
    ) {
        $article = $this->resolveArticle($accessService);
        $videoStep = $article->steps->firstWhere('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $generations = $article->generations
            ->where('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM)
            ->sortByDesc('generated_at')
            ->values();
        $selectedGeneration = $generations->firstWhere('id', $this->selectedGenerationId) ?: $generations->first();

        return view('livewire.admin.content-management.content-article-video-instagram-panel', [
            'article' => $article,
            'videoStep' => $videoStep,
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
                'contentImport.empresa',
                'steps.readyBy',
                'generations.templateVersion.masterTemplate',
                'generations.generatedBy',
            ])
            ->findOrFail((int) $this->articleId);

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $article;
    }
}
