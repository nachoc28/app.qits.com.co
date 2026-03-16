<?php

namespace App\Services\Seo;

/**
 * Resultado estructurado de una ejecución de sincronización GA4.
 */
final class Ga4SyncResult
{
    /** @var int */
    public $dailyRows;

    /** @var int */
    public $landingRows;

    /** @var bool */
    public $synced;

    public function __construct(int $dailyRows, int $landingRows, bool $synced = true)
    {
        $this->dailyRows   = $dailyRows;
        $this->landingRows = $landingRows;
        $this->synced      = $synced;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'synced' => $this->synced,
            'daily_rows' => $this->dailyRows,
            'landing_rows' => $this->landingRows,
        ];
    }
}
