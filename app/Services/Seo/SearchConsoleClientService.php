<?php

namespace App\Services\Seo;

use App\Services\Google\GoogleClientFactory;
use App\Services\Google\GoogleOAuthTokenService;
use RuntimeException;
use Illuminate\Support\Carbon;

/**
 * Cliente de Google Search Console para SIGC QITS.
 *
 * Responsabilidad única:
 *   Obtener datos crudos de la API de GSC para una propiedad concreta.
 *   No persiste nada, no formatea para pantalla, no toma decisiones de negocio.
 *
 * Quién llama a este servicio:
 *   - Jobs de sincronización (SyncGscDailyMetricsJob, etc.).
 *   - No se inyecta directamente en controladores ni componentes Livewire.
 *
 * Autenticación:
 *   Se delega a la capa global GoogleOAuthTokenService + GoogleClientFactory.
 *   Este servicio no contiene lógica OAuth ni lectura directa de secretos.
 */
class SearchConsoleClientService
{
    /** @var GoogleClientFactory */
    private $googleFactory;

    /** @var GoogleOAuthTokenService */
    private $tokenService;

    public function __construct(GoogleClientFactory $googleFactory, GoogleOAuthTokenService $tokenService)
    {
        $this->googleFactory = $googleFactory;
        $this->tokenService = $tokenService;
    }

    /**
     * Obtiene métricas agregadas diarias (clics, impresiones, CTR, posición media)
     * para el rango de fechas indicado.
     *
     * @param  SeoPropertyContext  $context  Configuración SEO de la empresa.
     * @param  Carbon              $from     Inicio del periodo (inclusive).
     * @param  Carbon              $to       Fin del periodo (inclusive).
     *
     * @return array<int, array<string, mixed>>
     *   Cada elemento: ['date' => '2026-03-01', 'clicks' => 120, 'impressions' => 4000,
     *                    'ctr' => 0.0300, 'avg_position' => 8.20]
     */
    public function fetchDailyMetrics(
        SeoPropertyContext $context,
        Carbon $from,
        Carbon $to
    ): array {
        $rows = $this->runQuery(
            (string) $context->gscProperty(),
            $from,
            $to,
            ['date'],
            $this->defaultRowLimit()
        );

        $out = [];
        foreach ($rows as $row) {
            $keys = $row['keys'];
            if (! isset($keys[0]) || $keys[0] === '') {
                continue;
            }

            $out[] = [
                'date'         => (string) $keys[0],
                'clicks'       => (int) $row['clicks'],
                'impressions'  => (int) $row['impressions'],
                'ctr'          => (float) $row['ctr'],
                'avg_position' => (float) $row['position'],
            ];
        }

        usort($out, function (array $a, array $b): int {
            return strcmp($a['date'], $b['date']);
        });

        return $out;
    }

    /**
     * Obtiene el ranking de queries (palabras clave) para el periodo dado.
     *
     * @param  int  $limit  Máximo de filas a devolver (rowLimit en la API).
     *
     * @return array<int, array<string, mixed>>
     *   Cada elemento: ['query' => 'diseño web bogota', 'page' => 'https://....',
     *                    'clicks' => 10, 'impressions' => 320, 'ctr' => 0.0312, 'avg_position' => 5.4]
     */
    public function fetchTopQueries(
        SeoPropertyContext $context,
        Carbon $from,
        Carbon $to,
        int $limit = 100
    ): array {
        $rows = $this->runQuery(
            (string) $context->gscProperty(),
            $from,
            $to,
            ['date', 'query', 'page'],
            $limit
        );

        $out = [];
        foreach ($rows as $row) {
            $keys = $row['keys'];
            if (! isset($keys[0]) || ! isset($keys[1])) {
                continue;
            }

            $out[] = [
                'date'         => (string) $keys[0],
                'query'        => (string) $keys[1],
                'page'         => isset($keys[2]) ? (string) $keys[2] : null,
                'clicks'       => (int) $row['clicks'],
                'impressions'  => (int) $row['impressions'],
                'ctr'          => (float) $row['ctr'],
                'avg_position' => (float) $row['position'],
            ];
        }

        return $out;
    }

    /**
     * Obtiene el ranking de páginas por rendimiento para el periodo dado.
     *
     * @param  int  $limit  Máximo de filas a devolver.
     *
     * @return array<int, array<string, mixed>>
     *   Cada elemento: ['page' => 'https://...', 'clicks' => 80,
     *                    'impressions' => 2800, 'ctr' => 0.0285, 'avg_position' => 7.1]
     */
    public function fetchTopPages(
        SeoPropertyContext $context,
        Carbon $from,
        Carbon $to,
        int $limit = 100
    ): array {
        $rows = $this->runQuery(
            (string) $context->gscProperty(),
            $from,
            $to,
            ['date', 'page'],
            $limit
        );

        $out = [];
        foreach ($rows as $row) {
            $keys = $row['keys'];
            if (! isset($keys[0]) || ! isset($keys[1])) {
                continue;
            }

            $out[] = [
                'date'         => (string) $keys[0],
                'page'         => (string) $keys[1],
                'clicks'       => (int) $row['clicks'],
                'impressions'  => (int) $row['impressions'],
                'ctr'          => (float) $row['ctr'],
                'avg_position' => (float) $row['position'],
            ];
        }

        return $out;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /** @return mixed */
    protected function searchConsoleService()
    {
        $client = $this->googleFactory->makeSearchConsoleClient();
        $this->tokenService->refreshAccessToken($client);

        if (class_exists('Google\\Service\\Webmasters')) {
            $class = 'Google\\Service\\Webmasters';
            return new $class($client);
        }

        if (class_exists('Google_Service_Webmasters')) {
            $legacyClass = 'Google_Service_Webmasters';
            return new $legacyClass($client);
        }

        throw new RuntimeException('Google Webmasters service class is not available.');
    }

    /**
     * Ejecuta una consulta searchAnalytics.query y devuelve filas normalizadas.
     *
     * @param  string[]  $dimensions
     * @return array<int, array{keys: array<int, string>, clicks: float, impressions: float, ctr: float, position: float}>
     */
    private function runQuery(
        string $property,
        Carbon $from,
        Carbon $to,
        array $dimensions,
        int $rowLimit
    ): array {
        if ($property === '') {
            throw new RuntimeException('Search Console property vacío o no configurado.');
        }

        $service = $this->searchConsoleService();

        $payload = [
            'startDate'  => $from->toDateString(),
            'endDate'    => $to->toDateString(),
            'dimensions' => $dimensions,
            'rowLimit'   => $rowLimit > 0 ? $rowLimit : $this->defaultRowLimit(),
            'startRow'   => 0,
        ];

        $request = $this->buildRequestObject($payload);

        $response = $service->searchanalytics->query($property, $request);
        $rows = method_exists($response, 'getRows') ? $response->getRows() : [];

        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = $this->normalizeRow($row);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function buildRequestObject(array $payload)
    {
        $requestClass = null;

        if (class_exists('Google\\Service\\Webmasters\\SearchAnalyticsQueryRequest')) {
            $requestClass = 'Google\\Service\\Webmasters\\SearchAnalyticsQueryRequest';
        } elseif (class_exists('Google_Service_Webmasters_SearchAnalyticsQueryRequest')) {
            $requestClass = 'Google_Service_Webmasters_SearchAnalyticsQueryRequest';
        }

        if ($requestClass === null) {
            throw new RuntimeException('No se encontró SearchAnalyticsQueryRequest en google/apiclient.');
        }

        $request = new $requestClass();

        foreach ($payload as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($request, $setter)) {
                $request->{$setter}($value);
            }
        }

        return $request;
    }

    /**
     * @param  mixed  $row
     * @return array{keys: array<int, string>, clicks: float, impressions: float, ctr: float, position: float}
     */
    private function normalizeRow($row): array
    {
        if (is_array($row)) {
            return [
                'keys'        => isset($row['keys']) && is_array($row['keys']) ? array_values($row['keys']) : [],
                'clicks'      => isset($row['clicks']) ? (float) $row['clicks'] : 0.0,
                'impressions' => isset($row['impressions']) ? (float) $row['impressions'] : 0.0,
                'ctr'         => isset($row['ctr']) ? (float) $row['ctr'] : 0.0,
                'position'    => isset($row['position']) ? (float) $row['position'] : 0.0,
            ];
        }

        return [
            'keys'        => method_exists($row, 'getKeys') && is_array($row->getKeys()) ? array_values($row->getKeys()) : [],
            'clicks'      => method_exists($row, 'getClicks') ? (float) $row->getClicks() : 0.0,
            'impressions' => method_exists($row, 'getImpressions') ? (float) $row->getImpressions() : 0.0,
            'ctr'         => method_exists($row, 'getCtr') ? (float) $row->getCtr() : 0.0,
            'position'    => method_exists($row, 'getPosition') ? (float) $row->getPosition() : 0.0,
        ];
    }

    private function defaultRowLimit(): int
    {
        $limit = (int) config('seo.sync.gsc_row_limit', 25000);

        return $limit > 0 ? $limit : 25000;
    }
}
