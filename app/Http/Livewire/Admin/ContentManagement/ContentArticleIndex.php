<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class ContentArticleIndex extends Component
{
    use WithPagination;

    public const PERIOD_ALL = 'all';
    public const PERIOD_CURRENT_MONTH = 'current_month';
    public const PERIOD_PREVIOUS_MONTH = 'previous_month';
    public const PERIOD_NEXT_MONTH = 'next_month';

    protected $paginationTheme = 'tailwind';

    /** @var string */
    public $search = '';

    /** @var int|string|null */
    public $selectedEmpresaId = '';

    /** @var string */
    public $selectedMainStatus = '';

    /** @var string */
    public $selectedPeriod = self::PERIOD_ALL;

    /** @var int */
    public $perPage = 10;

    /** @var array<int, array{id:int,nombre:string}> */
    public $authorizedEmpresas = [];

    /** @var bool */
    public $hasMultipleEmpresas = false;

    public function mount(ContentAccessService $accessService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        /** @var User $user */
        $user = auth()->user();
        $empresas = $accessService->authorizedEmpresas($user);

        if ($empresas->isEmpty()) {
            abort(403);
        }

        $this->authorizedEmpresas = $empresas
            ->map(static function ($empresa): array {
                return [
                    'id' => (int) $empresa->id,
                    'nombre' => (string) $empresa->nombre,
                ];
            })
            ->values()
            ->all();

        $this->hasMultipleEmpresas = count($this->authorizedEmpresas) > 1;

        if (! $user->isAdmin()) {
            $this->selectedEmpresaId = $this->authorizedEmpresas[0]['id'] ?? '';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedEmpresaId(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedMainStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedPeriod(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function render(ContentAccessService $accessService)
    {
        return view('livewire.admin.content-management.content-article-index', [
            'articles' => $this->articles($accessService),
            'mainStatusOptions' => ContentArticle::MAIN_STATUSES,
            'periodOptions' => [
                self::PERIOD_ALL => 'Todos',
                self::PERIOD_CURRENT_MONTH => 'Mes actual',
                self::PERIOD_PREVIOUS_MONTH => 'Mes anterior',
                self::PERIOD_NEXT_MONTH => 'Mes siguiente',
            ],
        ]);
    }

    private function articles(ContentAccessService $accessService): LengthAwarePaginator
    {
        /** @var User $user */
        $user = auth()->user();
        $query = $accessService->visibleArticlesQuery($user)
            ->select('content_articles.*');

        $this->applyEmpresaFilter($query);
        $this->applySearch($query);
        $this->applyMainStatusFilter($query);
        $this->applyPeriodFilter($query);
        $this->applyPriorityOrdering($query);

        return $query->paginate($this->perPage);
    }

    private function applyEmpresaFilter(Builder $query): void
    {
        $empresaId = (int) $this->selectedEmpresaId;
        $authorizedIds = collect($this->authorizedEmpresas)->pluck('id')->map(static function ($id): int {
            return (int) $id;
        })->all();

        if ($empresaId <= 0) {
            return;
        }

        if (! in_array($empresaId, $authorizedIds, true)) {
            abort(403);
        }

        $query->whereHas('contentImport', function (Builder $builder) use ($empresaId): void {
            $builder->where('empresa_id', $empresaId);
        });
    }

    private function applySearch(Builder $query): void
    {
        $search = trim($this->search);

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder->where('content_articles.topic', 'like', '%' . $search . '%')
                ->orWhereHas('contentImport.empresa', function (Builder $empresaQuery) use ($search): void {
                    $empresaQuery->where('nombre', 'like', '%' . $search . '%');
                });
        });
    }

    private function applyMainStatusFilter(Builder $query): void
    {
        if ($this->selectedMainStatus === '' || ! in_array($this->selectedMainStatus, ContentArticle::MAIN_STATUSES, true)) {
            return;
        }

        $query->where('content_articles.main_status', $this->selectedMainStatus);
    }

    private function applyPeriodFilter(Builder $query): void
    {
        [$from, $to] = $this->periodRange();

        if (! $from || ! $to) {
            return;
        }

        $query->whereBetween('content_articles.article_date', [
            $from->toDateString(),
            $to->toDateString(),
        ]);
    }

    private function applyPriorityOrdering(Builder $query): void
    {
        $currentStart = now()->startOfMonth()->toDateString();
        $currentEnd = now()->endOfMonth()->toDateString();

        $query->orderByRaw(
            "case
                when content_articles.main_status = ? then 1
                when content_articles.main_status = ? and content_articles.article_date between ? and ? then 2
                when content_articles.main_status = ? then 3
                when content_articles.main_status = ? then 4
                else 5
            end asc",
            [
                ContentArticle::MAIN_STATUS_PROCESSING,
                ContentArticle::MAIN_STATUS_UNPUBLISHED,
                $currentStart,
                $currentEnd,
                ContentArticle::MAIN_STATUS_UNPUBLISHED,
                ContentArticle::MAIN_STATUS_PUBLISHED,
            ]
        )->orderByRaw(
            "case
                when content_articles.main_status = ? then content_articles.updated_at
                else null
            end desc",
            [ContentArticle::MAIN_STATUS_PROCESSING]
        )->orderByRaw(
            "case
                when content_articles.main_status = ? and content_articles.article_date between ? and ? then content_articles.article_date
                else null
            end asc",
            [
                ContentArticle::MAIN_STATUS_UNPUBLISHED,
                $currentStart,
                $currentEnd,
            ]
        )->orderByRaw(
            "case
                when content_articles.main_status = ? and not (content_articles.article_date between ? and ?) then content_articles.article_date
                else null
            end asc",
            [
                ContentArticle::MAIN_STATUS_UNPUBLISHED,
                $currentStart,
                $currentEnd,
            ]
        )->orderByRaw(
            "case
                when content_articles.main_status = ? then content_articles.published_at
                else null
            end desc",
            [ContentArticle::MAIN_STATUS_PUBLISHED]
        )->orderByDesc('content_articles.id');
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function periodRange(): array
    {
        $now = now();

        if ($this->selectedPeriod === self::PERIOD_CURRENT_MONTH) {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        if ($this->selectedPeriod === self::PERIOD_PREVIOUS_MONTH) {
            $month = $now->copy()->subMonthNoOverflow();

            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        }

        if ($this->selectedPeriod === self::PERIOD_NEXT_MONTH) {
            $month = $now->copy()->addMonthNoOverflow();

            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        }

        return [null, null];
    }
}
