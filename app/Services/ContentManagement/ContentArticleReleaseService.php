<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContentArticleReleaseService
{
    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function deliveryAvailability(ContentArticle $article): array
    {
        if (! $article->files()->exists()) {
            return [
                'allowed' => false,
                'message' => 'Debe existir al menos un archivo final antes de marcar la entrega.',
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
    }

    public function markDelivered(ContentArticle $article, User $user): ContentArticle
    {
        $availability = $this->deliveryAvailability($article);

        if (! $availability['allowed']) {
            throw new RuntimeException((string) $availability['message']);
        }

        return DB::transaction(function () use ($article, $user): ContentArticle {
            $lockedArticle = $this->lockArticle($article->id);

            $lockedArticle->forceFill([
                'delivered_at' => now(),
                'delivered_by' => $user->id,
            ])->save();

            return $lockedArticle->fresh(['deliveredBy', 'publishedBy']);
        });
    }

    public function unmarkDelivered(ContentArticle $article): ContentArticle
    {
        return DB::transaction(function () use ($article): ContentArticle {
            $lockedArticle = $this->lockArticle($article->id);

            $lockedArticle->forceFill([
                'delivered_at' => null,
                'delivered_by' => null,
            ])->save();

            return $lockedArticle->fresh(['deliveredBy', 'publishedBy']);
        });
    }

    public function publish(ContentArticle $article, string $publishedUrl, User $user): ContentArticle
    {
        $normalizedUrl = $this->normalizePublishedUrl($publishedUrl);

        if (! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Debes ingresar una URL publicada valida.');
        }

        return DB::transaction(function () use ($article, $normalizedUrl, $user): ContentArticle {
            $lockedArticle = $this->lockArticle($article->id);

            $lockedArticle->forceFill([
                'published_at' => now(),
                'published_by' => $user->id,
                'published_url' => $normalizedUrl,
                'main_status' => ContentArticle::MAIN_STATUS_PUBLISHED,
                'operational_stage' => ContentArticle::STAGE_COMPLETED,
            ])->save();

            return $lockedArticle->fresh(['deliveredBy', 'publishedBy']);
        });
    }

    public function updatePublishedUrl(ContentArticle $article, string $publishedUrl): ContentArticle
    {
        $normalizedUrl = $this->normalizePublishedUrl($publishedUrl);

        if (! filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Debes ingresar una URL publicada valida.');
        }

        return DB::transaction(function () use ($article, $normalizedUrl): ContentArticle {
            $lockedArticle = $this->lockArticle($article->id);

            if (! $lockedArticle->published_at) {
                throw new RuntimeException('El articulo aun no ha sido publicado.');
            }

            $lockedArticle->forceFill([
                'published_url' => $normalizedUrl,
            ])->save();

            return $lockedArticle->fresh(['deliveredBy', 'publishedBy']);
        });
    }

    private function lockArticle(int $articleId): ContentArticle
    {
        return ContentArticle::query()
            ->whereKey($articleId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function normalizePublishedUrl(string $publishedUrl): string
    {
        return trim($publishedUrl);
    }
}
