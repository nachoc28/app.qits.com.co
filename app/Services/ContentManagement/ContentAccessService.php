<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContentAccessService
{
    /**
     * @return Collection<int, Empresa>
     */
    public function authorizedEmpresas(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Empresa::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        if ((int) $user->empresa_id > 0) {
            return Empresa::query()
                ->where('id', (int) $user->empresa_id)
                ->get(['id', 'nombre']);
        }

        return collect();
    }

    /**
     * @return array<int, int>
     */
    public function authorizedEmpresaIds(User $user): array
    {
        return $this->authorizedEmpresas($user)
            ->pluck('id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();
    }

    public function visibleArticlesQuery(User $user): Builder
    {
        $query = ContentArticle::query()
            ->with(['contentImport.empresa']);

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('contentImport', function (Builder $builder) use ($user): void {
            $builder->where('empresa_id', (int) $user->empresa_id);
        });
    }

    public function canAccessArticle(User $user, ContentArticle $article): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) optional($article->contentImport)->empresa_id === (int) $user->empresa_id;
    }
}
