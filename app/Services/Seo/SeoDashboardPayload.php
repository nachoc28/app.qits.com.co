<?php

namespace App\Services\Seo;

use Illuminate\Support\Carbon;

/**
 * DTO de salida para el dashboard SEO por empresa.
 *
 * Estructura orientada a consumo directo desde componentes Livewire.
 */
final class SeoDashboardPayload
{
    /** @var Carbon */
    public $from;

    /** @var Carbon */
    public $to;

    /** @var array<string, mixed> */
    public $kpis = [];

    /** @var array<int, array<string, mixed>> */
    public $topQueries = [];

    /** @var array<int, array<string, mixed>> */
    public $topLandingPages = [];

    /** @var array<int, array<string, mixed>> */
    public $recentUtmConversions = [];

    /** @var array<string, mixed> */
    public $trends = [];

    public function __construct(Carbon $from, Carbon $to)
    {
        $this->from = $from;
        $this->to   = $to;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'period' => [
                'from' => $this->from->toDateString(),
                'to'   => $this->to->toDateString(),
            ],
            'kpis' => $this->kpis,
            'top_queries' => $this->topQueries,
            'top_landing_pages' => $this->topLandingPages,
            'recent_utm_conversions' => $this->recentUtmConversions,
            'trends' => $this->trends,
        ];
    }
}
