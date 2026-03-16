<?php

namespace App\Jobs\Seo;

use App\Models\EmpresaSeoProperty;
use App\Services\Seo\Ga4SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncEmpresaGa4Job implements ShouldQueue
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

    public function handle(Ga4SyncService $syncService): void
    {
        $property = $this->seoProperty->fresh(['empresa']);

        if (! $property instanceof EmpresaSeoProperty || ! $property->empresa) {
            Log::warning('[SEO][GA4] Propiedad SEO no encontrada para job.', [
                'empresa_seo_property_id' => $this->seoProperty->id,
            ]);
            return;
        }

        if (! $property->isGa4Ready()) {
            Log::info('[SEO][GA4] Sync omitido: integración GA4 no está lista.', [
                'empresa_id' => $property->empresa_id,
                'empresa_seo_property_id' => $property->id,
            ]);
            return;
        }

        [$from, $to] = $this->resolveRange();

        try {
            $result = $syncService->syncEmpresa($property->empresa, $from, $to);

            Log::info('[SEO][GA4] Sync exitoso.', [
                'empresa_id' => $property->empresa_id,
                'empresa_seo_property_id' => $property->id,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'daily_rows' => $result->dailyRows,
                'landing_rows' => $result->landingRows,
            ]);
        } catch (Throwable $e) {
            Log::error('[SEO][GA4] Error durante sincronización.', [
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
        Log::critical('[SEO][GA4] Job agotó reintentos.', [
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

        $lookback = max((int) config('seo.sync.ga4_lookback_days', 3), 1);

        $to = now()->startOfDay();
        $from = now()->subDays($lookback - 1)->startOfDay();

        return [$from, $to];
    }
}
