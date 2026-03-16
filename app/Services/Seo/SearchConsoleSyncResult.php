<?php

namespace App\Services\Seo;

/**
 * Resultado estructurado de una ejecución de sincronización GSC.
 */
final class SearchConsoleSyncResult
{
    /** @var int */
    public $dailyRows;

    /** @var int */
    public $queryRows;

    /** @var int */
    public $pageRows;

    /** @var bool */
    public $synced;

    public function __construct(int $dailyRows, int $queryRows, int $pageRows, bool $synced = true)
    {
        $this->dailyRows = $dailyRows;
        $this->queryRows = $queryRows;
        $this->pageRows  = $pageRows;
        $this->synced    = $synced;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'synced' => $this->synced,
            'daily_rows' => $this->dailyRows,
            'query_rows' => $this->queryRows,
            'page_rows' => $this->pageRows,
        ];
    }
}
