<?php

namespace App\Services\Seo;

use App\Services\Google\GoogleClientFactory;
use App\Services\Google\GoogleOAuthTokenService;
use RuntimeException;
use Illuminate\Support\Carbon;

/**
 * Cliente de Google Analytics 4 (Data API v1beta) para SIGC QITS.
 *
 * Responsabilidad única:
 *   Obtener datos crudos de la GA4 Data API para un property ID concreto.
 *   No persiste nada, no formatea para pantalla, no toma decisiones de negocio.
 *
 * Quién llama a este servicio:
 *   - Jobs de sincronización (SyncGa4DailyMetricsJob, etc.).
 *   - No se inyecta directamente en controladores ni componentes Livewire.
 *
 * Autenticación:
 *   Se delega a la capa global GoogleOAuthTokenService + GoogleClientFactory.
 *   Este servicio no contiene lógica OAuth ni lectura directa de secretos.
 *
 * Nota: esta clase es exclusiva para GA4. No soporta Universal Analytics.
 *
 * TODO: instalar google/analytics-data y configurar credenciales en .env.
 */
class Ga4ClientService
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
     * Obtiene métricas agregadas diarias (usuarios, sesiones, conversiones,
     * sesiones orgánicas) para el rango de fechas indicado.
     *
     * @param  SeoPropertyContext  $context  Configuración SEO de la empresa.
     * @param  Carbon              $from     Inicio del periodo (inclusive).
     * @param  Carbon              $to       Fin del periodo (inclusive).
     *
     * @return array<int, array<string, mixed>>
     *   Cada elemento: ['date' => '2026-03-01', 'users' => 312, 'sessions' => 410,
     *                    'engaged_sessions' => 280, 'conversions' => 12,
     *                    'organic_sessions' => 195]
     */
    public function fetchDailyMetrics(
        SeoPropertyContext $context,
        Carbon $from,
        Carbon $to
    ): array {
        return $this->fetchDailyMetricsByProperty((string) $context->ga4PropertyId(), $from, $to);
    }

    /**
     * Obtiene métricas agregadas diarias para una propiedad GA4 específica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchDailyMetricsByProperty(string $ga4PropertyId, Carbon $from, Carbon $to): array
    {
        $property = $this->normalizePropertyName($ga4PropertyId);

        $rows = $this->runReportWithMetricFallback(
            $property,
            $from,
            $to,
            ['date'],
            [
                ['activeUsers', 'sessions', 'engagedSessions', 'keyEvents'],
                ['activeUsers', 'sessions', 'engagedSessions', 'conversions'],
                ['activeUsers', 'sessions', 'engagedSessions'],
            ],
            null,
            0
        );

        $organicRows = $this->runReportWithMetricFallback(
            $property,
            $from,
            $to,
            ['date'],
            [
                ['sessions'],
            ],
            $this->buildOrganicSearchFilter(),
            0
        );

        $organicByDate = [];
        foreach ($organicRows as $row) {
            if (! isset($row['keys'][0])) {
                continue;
            }
            $organicByDate[(string) $row['keys'][0]] = (int) ($row['metrics'][0] ?? 0);
        }

        $out = [];
        foreach ($rows as $row) {
            $date = isset($row['keys'][0]) ? (string) $row['keys'][0] : null;

            if ($date === null || $date === '') {
                continue;
            }

            $conversionValue = 0;
            if (isset($row['metrics'][3])) {
                $conversionValue = (int) round((float) $row['metrics'][3]);
            }

            $out[] = [
                'date'             => $date,
                'users'            => isset($row['metrics'][0]) ? (int) round((float) $row['metrics'][0]) : 0,
                'sessions'         => isset($row['metrics'][1]) ? (int) round((float) $row['metrics'][1]) : 0,
                'engaged_sessions' => isset($row['metrics'][2]) ? (int) round((float) $row['metrics'][2]) : null,
                'conversions'      => $conversionValue,
                'organic_sessions' => $organicByDate[$date] ?? null,
            ];
        }

        usort($out, function (array $a, array $b): int {
            return strcmp($a['date'], $b['date']);
        });

        return $out;
    }

    /**
     * Obtiene el ranking de landing pages por tráfico orgánico para el periodo.
     *
     * @param  int  $limit  Máximo de filas a devolver.
     *
     * @return array<int, array<string, mixed>>
     *   Cada elemento: ['landing_page' => '/blog/post', 'users' => 90,
     *                    'sessions' => 110, 'conversions' => 3,
     *                    'engagement_rate' => 0.7182]
     */
    public function fetchLandingPages(
        SeoPropertyContext $context,
        Carbon $from,
        Carbon $to,
        int $limit = 100
    ): array {
        return $this->fetchLandingPagesByProperty((string) $context->ga4PropertyId(), $from, $to, $limit);
    }

    /**
     * Obtiene landing pages para una propiedad GA4 específica.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLandingPagesByProperty(
        string $ga4PropertyId,
        Carbon $from,
        Carbon $to,
        int $limit = 100
    ): array {
        $property = $this->normalizePropertyName($ga4PropertyId);

        $rows = $this->runReportWithMetricFallback(
            $property,
            $from,
            $to,
            ['date', 'landingPagePlusQueryString'],
            [
                ['activeUsers', 'sessions', 'keyEvents', 'engagementRate'],
                ['activeUsers', 'sessions', 'conversions', 'engagementRate'],
                ['activeUsers', 'sessions', 'engagementRate'],
            ],
            $this->buildOrganicSearchFilter(),
            $limit
        );

        $out = [];
        foreach ($rows as $row) {
            $date = isset($row['keys'][0]) ? (string) $row['keys'][0] : null;
            $landing = isset($row['keys'][1]) ? (string) $row['keys'][1] : null;

            if ($date === null || $date === '' || $landing === null || $landing === '') {
                continue;
            }

            $conversions = null;
            $engagementRate = null;

            if (count($row['metrics']) >= 4) {
                $conversions = (int) round((float) $row['metrics'][2]);
                $engagementRate = (float) $row['metrics'][3];
            } elseif (count($row['metrics']) === 3) {
                $engagementRate = (float) $row['metrics'][2];
            }

            $out[] = [
                'date'            => $date,
                'landing_page'    => $landing,
                'users'           => isset($row['metrics'][0]) ? (int) round((float) $row['metrics'][0]) : null,
                'sessions'        => isset($row['metrics'][1]) ? (int) round((float) $row['metrics'][1]) : null,
                'conversions'     => $conversions,
                'engagement_rate' => $engagementRate,
            ];
        }

        return $out;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /** @return mixed */
    protected function ga4DataService()
    {
        $client = $this->googleFactory->makeGa4Client();
        $this->tokenService->refreshAccessToken($client);

        if (class_exists('Google\\Service\\AnalyticsData')) {
            $class = 'Google\\Service\\AnalyticsData';
            return new $class($client);
        }

        if (class_exists('Google_Service_AnalyticsData')) {
            $legacyClass = 'Google_Service_AnalyticsData';
            return new $legacyClass($client);
        }

        throw new RuntimeException('Google AnalyticsData service class is not available.');
    }

    /**
     * Ejecuta runReport con fallback de métricas para compatibilidad entre propiedades.
     *
     * @param  string[]  $dimensions
     * @param  array<int, array<int, string>>  $metricSets
     * @param  mixed|null  $dimensionFilter
     * @return array<int, array{keys: array<int, string>, metrics: array<int, string>}>
     */
    private function runReportWithMetricFallback(
        string $property,
        Carbon $from,
        Carbon $to,
        array $dimensions,
        array $metricSets,
        $dimensionFilter,
        int $limit
    ): array {
        $lastException = null;

        foreach ($metricSets as $metricNames) {
            try {
                return $this->runReport($property, $from, $to, $dimensions, $metricNames, $dimensionFilter, $limit);
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if ($lastException) {
            throw new RuntimeException(
                'GA4 runReport falló para todos los conjuntos de métricas: ' . $lastException->getMessage(),
                0,
                $lastException
            );
        }

        return [];
    }

    /**
     * @param  string[]  $dimensions
     * @param  string[]  $metricNames
     * @param  mixed|null  $dimensionFilter
     * @return array<int, array{keys: array<int, string>, metrics: array<int, string>}>
     */
    private function runReport(
        string $property,
        Carbon $from,
        Carbon $to,
        array $dimensions,
        array $metricNames,
        $dimensionFilter,
        int $limit
    ): array {
        $service = $this->ga4DataService();

        $dateRange = $this->newDateRange($from->toDateString(), $to->toDateString());

        $dimensionObjects = [];
        foreach ($dimensions as $dimensionName) {
            $dimensionObjects[] = $this->newDimension($dimensionName);
        }

        $metricObjects = [];
        foreach ($metricNames as $metricName) {
            $metricObjects[] = $this->newMetric($metricName);
        }

        $request = $this->newRunReportRequest();
        $this->setIfMethodExists($request, 'setDateRanges', [$dateRange]);
        $this->setIfMethodExists($request, 'setDimensions', $dimensionObjects);
        $this->setIfMethodExists($request, 'setMetrics', $metricObjects);

        if ($dimensionFilter !== null) {
            $this->setIfMethodExists($request, 'setDimensionFilter', $dimensionFilter);
        }

        if ($limit > 0) {
            $this->setIfMethodExists($request, 'setLimit', (string) $limit);
        }

        $response = $service->properties->runReport($property, $request);
        $rows = method_exists($response, 'getRows') ? $response->getRows() : [];

        if (! is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = $this->normalizeReportRow($row);
        }

        return $normalized;
    }

    /** @return mixed */
    private function newRunReportRequest()
    {
        if (class_exists('Google\\Service\\AnalyticsData\\RunReportRequest')) {
            $class = 'Google\\Service\\AnalyticsData\\RunReportRequest';
            return new $class();
        }

        if (class_exists('Google_Service_AnalyticsData_RunReportRequest')) {
            $class = 'Google_Service_AnalyticsData_RunReportRequest';
            return new $class();
        }

        throw new RuntimeException('No se encontró RunReportRequest de GA4 Data API.');
    }

    /** @return mixed */
    private function newDateRange(string $startDate, string $endDate)
    {
        if (class_exists('Google\\Service\\AnalyticsData\\DateRange')) {
            $class = 'Google\\Service\\AnalyticsData\\DateRange';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setStartDate', $startDate);
            $this->setIfMethodExists($obj, 'setEndDate', $endDate);
            return $obj;
        }

        if (class_exists('Google_Service_AnalyticsData_DateRange')) {
            $class = 'Google_Service_AnalyticsData_DateRange';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setStartDate', $startDate);
            $this->setIfMethodExists($obj, 'setEndDate', $endDate);
            return $obj;
        }

        throw new RuntimeException('No se encontró DateRange de GA4 Data API.');
    }

    /** @return mixed */
    private function newMetric(string $name)
    {
        if (class_exists('Google\\Service\\AnalyticsData\\Metric')) {
            $class = 'Google\\Service\\AnalyticsData\\Metric';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setName', $name);
            return $obj;
        }

        if (class_exists('Google_Service_AnalyticsData_Metric')) {
            $class = 'Google_Service_AnalyticsData_Metric';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setName', $name);
            return $obj;
        }

        throw new RuntimeException('No se encontró Metric de GA4 Data API.');
    }

    /** @return mixed */
    private function newDimension(string $name)
    {
        if (class_exists('Google\\Service\\AnalyticsData\\Dimension')) {
            $class = 'Google\\Service\\AnalyticsData\\Dimension';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setName', $name);
            return $obj;
        }

        if (class_exists('Google_Service_AnalyticsData_Dimension')) {
            $class = 'Google_Service_AnalyticsData_Dimension';
            $obj = new $class();
            $this->setIfMethodExists($obj, 'setName', $name);
            return $obj;
        }

        throw new RuntimeException('No se encontró Dimension de GA4 Data API.');
    }

    /** @return mixed */
    private function buildOrganicSearchFilter()
    {
        $value = (string) config('seo.ga4.organic_channel_group', 'Organic Search');

        if (class_exists('Google\\Service\\AnalyticsData\\StringFilter')
            && class_exists('Google\\Service\\AnalyticsData\\Filter')
            && class_exists('Google\\Service\\AnalyticsData\\FilterExpression')) {
            $stringFilterClass = 'Google\\Service\\AnalyticsData\\StringFilter';
            $filterClass = 'Google\\Service\\AnalyticsData\\Filter';
            $exprClass = 'Google\\Service\\AnalyticsData\\FilterExpression';

            $stringFilter = new $stringFilterClass();
            $this->setIfMethodExists($stringFilter, 'setMatchType', 'EXACT');
            $this->setIfMethodExists($stringFilter, 'setValue', $value);

            $filter = new $filterClass();
            $this->setIfMethodExists($filter, 'setFieldName', 'sessionDefaultChannelGroup');
            $this->setIfMethodExists($filter, 'setStringFilter', $stringFilter);

            $expr = new $exprClass();
            $this->setIfMethodExists($expr, 'setFilter', $filter);

            return $expr;
        }

        if (class_exists('Google_Service_AnalyticsData_StringFilter')
            && class_exists('Google_Service_AnalyticsData_Filter')
            && class_exists('Google_Service_AnalyticsData_FilterExpression')) {
            $stringFilterClass = 'Google_Service_AnalyticsData_StringFilter';
            $filterClass = 'Google_Service_AnalyticsData_Filter';
            $exprClass = 'Google_Service_AnalyticsData_FilterExpression';

            $stringFilter = new $stringFilterClass();
            $this->setIfMethodExists($stringFilter, 'setMatchType', 'EXACT');
            $this->setIfMethodExists($stringFilter, 'setValue', $value);

            $filter = new $filterClass();
            $this->setIfMethodExists($filter, 'setFieldName', 'sessionDefaultChannelGroup');
            $this->setIfMethodExists($filter, 'setStringFilter', $stringFilter);

            $expr = new $exprClass();
            $this->setIfMethodExists($expr, 'setFilter', $filter);

            return $expr;
        }

        return null;
    }

    /**
     * @param  mixed  $row
     * @return array{keys: array<int, string>, metrics: array<int, string>}
     */
    private function normalizeReportRow($row): array
    {
        $keys = [];
        $metrics = [];

        if (is_array($row)) {
            $keys = isset($row['dimensionValues']) && is_array($row['dimensionValues'])
                ? $this->extractValuesFromArrayDimensionValues($row['dimensionValues'])
                : [];
            $metrics = isset($row['metricValues']) && is_array($row['metricValues'])
                ? $this->extractValuesFromArrayMetricValues($row['metricValues'])
                : [];
        } else {
            $dimensionValues = method_exists($row, 'getDimensionValues') ? $row->getDimensionValues() : [];
            $metricValues = method_exists($row, 'getMetricValues') ? $row->getMetricValues() : [];

            if (is_array($dimensionValues)) {
                foreach ($dimensionValues as $item) {
                    $keys[] = method_exists($item, 'getValue') ? (string) $item->getValue() : '';
                }
            }

            if (is_array($metricValues)) {
                foreach ($metricValues as $item) {
                    $metrics[] = method_exists($item, 'getValue') ? (string) $item->getValue() : '0';
                }
            }
        }

        return [
            'keys' => $keys,
            'metrics' => $metrics,
        ];
    }

    /** @param array<int, mixed> $items @return array<int, string> */
    private function extractValuesFromArrayDimensionValues(array $items): array
    {
        $values = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['value'])) {
                $values[] = (string) $item['value'];
            }
        }

        return $values;
    }

    /** @param array<int, mixed> $items @return array<int, string> */
    private function extractValuesFromArrayMetricValues(array $items): array
    {
        $values = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['value'])) {
                $values[] = (string) $item['value'];
            }
        }

        return $values;
    }

    private function normalizePropertyName(string $propertyId): string
    {
        $id = trim($propertyId);

        if ($id === '') {
            throw new RuntimeException('GA4 property ID vacío o no configurado.');
        }

        if (strpos($id, 'properties/') === 0) {
            return $id;
        }

        return 'properties/' . $id;
    }

    /** @param mixed $object @param mixed $value */
    private function setIfMethodExists($object, string $method, $value): void
    {
        if (method_exists($object, $method)) {
            $object->{$method}($value);
        }
    }
}
