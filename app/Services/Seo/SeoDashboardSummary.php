<?php

namespace App\Services\Seo;

use Illuminate\Support\Carbon;

/**
 * DTO mutable que transporta el resumen agregado del dashboard SEO.
 *
 * Se construye en SeoDashboardService::getSummary() y se consume
 * en el componente Livewire del dashboard por empresa.
 *
 * Todos los campos tienen valores por defecto seguros para que
 * el Blade pueda renderizar incluso cuando una fuente está deshabilitada.
 */
final class SeoDashboardSummary
{
    /** @var Carbon */
    public $from;

    /** @var Carbon */
    public $to;

    // ── Google Search Console ─────────────────────────────────────────────────

    /** @var bool */
    public $gscEnabled = false;

    /** @var int */
    public $gscClicks = 0;

    /** @var int */
    public $gscImpressions = 0;

    /** @var float */
    public $gscAvgCtr = 0.0;

    /** @var float */
    public $gscAvgPosition = 0.0;

    // ── Google Analytics 4 ────────────────────────────────────────────────────

    /** @var bool */
    public $ga4Enabled = false;

    /** @var int */
    public $ga4Users = 0;

    /** @var int */
    public $ga4Sessions = 0;

    /** @var int */
    public $ga4Conversions = 0;

    /** @var int */
    public $ga4OrganicSessions = 0;

    // ── UTM Tracker ───────────────────────────────────────────────────────────

    /** @var bool */
    public $utmEnabled = false;

    /** @var int */
    public $utmConversions = 0;

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
            'gsc' => [
                'enabled'      => $this->gscEnabled,
                'clicks'       => $this->gscClicks,
                'impressions'  => $this->gscImpressions,
                'avg_ctr'      => $this->gscAvgCtr,
                'avg_position' => $this->gscAvgPosition,
            ],
            'ga4' => [
                'enabled'          => $this->ga4Enabled,
                'users'            => $this->ga4Users,
                'sessions'         => $this->ga4Sessions,
                'conversions'      => $this->ga4Conversions,
                'organic_sessions' => $this->ga4OrganicSessions,
            ],
            'utm' => [
                'enabled'     => $this->utmEnabled,
                'conversions' => $this->utmConversions,
            ],
        ];
    }
}
