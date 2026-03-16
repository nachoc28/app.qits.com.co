<?php

namespace App\Jobs\Seo;

use App\Models\EmpresaSeoProperty;
use App\Services\Seo\SearchConsoleSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncEmpresaSearchConsoleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public EmpresaSeoProperty $seoProperty;
    public ?string $fromDate;
    public ?string $toDate;

    public int $tries = 3;

    public function __construct(EmpresaSeoProperty $seoProperty, ?string $fromDate = null, ?string $toDate = null)
    {
        $this->seoProperty = $seoProperty;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(SearchConsoleSyncService $syncService): void
    {
        $property = $this->seoProperty->fresh(['empresa']);

        if (! $property instanceof EmpresaSeoProperty || ! $property->empresa) {
            Log::warning('[SEO][GSC] Propiedad SEO no encontrada para job.', [
                'empresa_seo_property_id' => $this->seoProperty->id,
            ]);
            return;
        }

        if (! $property->isGscReady()) {
            Log::info('[SEO][GSC] Sync omitido: integración GSC no está lista.', [
                'empresa_id' => $property->empresa_id,
                'empresa_seo_property_id' => $property->id,
            ]);
            return;
        }

        [$from, $to] = $this->resolveRange();

        try {
            $result = $syncService->syncEmpresa($property->empresa, $from, $to);

            Log::info('[SEO][GSC] Sync exitoso.', [
                'empresa_id' => $property->empresa_id,
                'empresa_seo_property_id' => $property->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'daily_rows' => $result->dailyRows,
                'query_rows' => $result->queryRows,
                'page_rows' => $result->pageRows,
            ]);
        } catch (Throwable $e) {
            Log::error('[SEO][GSC] Error durante sincronización.', [
                'empresa_id' => $property->empresa_id,
                'empresa_seo_property_id' => $property->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('[SEO][GSC] Job agotó reintentos.', [
            'empresa_seo_property_id' => $this->seoProperty->id,
            'message' => $exception->getMessage(),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveRange(): array
    {
        if ($this->fromDate !== null && $this->toDate !== null) {
            return [
                Carbon::parse($this->fromDate)->startOfDay(),
                Carbon::parse($this->toDate)->startOfDay(),
            ];
        }

        $lookback = max((int) config('seo.sync.gsc_lookback_days', 16), 1);

        $to = now()->startOfDay();
        $from = now()->subDays($lookback - 1)->startOfDay();

        return [$from, $to];
    }
}
