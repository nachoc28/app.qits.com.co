<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentArticleReleaseService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

class ContentArticleDeliveryPublicationPanel extends Component
{
    /** @var int */
    public $articleId;

    /** @var string */
    public $publishedUrl = '';

    public function mount(int $articleId, ContentAccessService $accessService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        $this->articleId = $articleId;
        $article = $this->resolveArticle($accessService);
        $this->publishedUrl = (string) ($article->published_url ?? '');
    }

    public function markDelivered(
        ContentAccessService $accessService,
        ContentArticleReleaseService $releaseService
    ): void {
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        try {
            $releaseService->markDelivered($article, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'delivery' => $e->getMessage(),
            ]);
        }

        session()->flash('content_release_success', 'Entrega registrada correctamente.');
    }

    public function unmarkDelivered(
        ContentAccessService $accessService,
        ContentArticleReleaseService $releaseService
    ): void {
        $article = $this->resolveArticle($accessService);
        $releaseService->unmarkDelivered($article);

        session()->flash('content_release_success', 'Entrega corregida correctamente.');
    }

    public function publishArticle(
        ContentAccessService $accessService,
        ContentArticleReleaseService $releaseService
    ): void {
        $this->validate($this->publishedUrlRules(), $this->publishedUrlMessages());
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        try {
            $updated = $releaseService->publish($article, $this->publishedUrl, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'publishedUrl' => $e->getMessage(),
            ]);
        }

        $this->publishedUrl = (string) ($updated->published_url ?? '');
        session()->flash('content_release_success', 'Publicacion registrada correctamente.');
    }

    public function updatePublishedUrlAction(
        ContentAccessService $accessService,
        ContentArticleReleaseService $releaseService
    ): void {
        $this->validate($this->publishedUrlRules(), $this->publishedUrlMessages());
        $article = $this->resolveArticle($accessService);

        try {
            $updated = $releaseService->updatePublishedUrl($article, $this->publishedUrl);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'publishedUrl' => $e->getMessage(),
            ]);
        }

        $this->publishedUrl = (string) ($updated->published_url ?? '');
        session()->flash('content_release_success', 'URL publicada actualizada correctamente.');
    }

    public function render(
        ContentAccessService $accessService,
        ContentArticleReleaseService $releaseService
    ) {
        $article = $this->resolveArticle($accessService);

        return view('livewire.admin.content-management.content-article-delivery-publication-panel', [
            'article' => $article,
            'deliveryAvailability' => $releaseService->deliveryAvailability($article),
        ]);
    }

    private function publishedUrlRules(): array
    {
        return [
            'publishedUrl' => ['required', 'string', 'url'],
        ];
    }

    private function publishedUrlMessages(): array
    {
        return [
            'publishedUrl.required' => 'Debes ingresar la URL publicada.',
            'publishedUrl.url' => 'Debes ingresar una URL publicada valida.',
        ];
    }

    private function resolveArticle(ContentAccessService $accessService): ContentArticle
    {
        /** @var User $user */
        $user = auth()->user();

        $article = ContentArticle::query()
            ->with([
                'contentImport.empresa',
                'files.uploadedBy',
                'deliveredBy',
                'publishedBy',
            ])
            ->findOrFail((int) $this->articleId);

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $article;
    }
}
