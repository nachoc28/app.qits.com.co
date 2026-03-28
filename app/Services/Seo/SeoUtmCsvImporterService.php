<?php

namespace App\Services\Seo;

use App\Models\Empresa;
use App\Models\EmpresaSeoProperty;
use App\Models\SeoUtmConversion;
use Illuminate\Support\Carbon;

class SeoUtmCsvImporterService
{
    private const EXTERNAL_REFERENCE_DOMAINS = [
        'google.com',
        'facebook.com',
        'fb.com',
        'instagram.com',
        't.co',
        'twitter.com',
        'x.com',
        'linkedin.com',
        'bing.com',
        'youtube.com',
        'wa.me',
        'whatsapp.com',
        'api.whatsapp.com',
    ];

    private const REQUIRED_COLUMNS = [
        'id',
        'event_type',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'extra',
        'created_at',
    ];

    private const OPTIONAL_COLUMNS = [
        'sigc_synced_at',
        'sigc_last_attempt_at',
        'sigc_last_error',
        'form_name',
        'page_url',
        'event_name',
    ];

    private const EMPTY_TOKENS = [
        '',
        '-',
        '_',
        '(not set)',
        '(none)',
        '(direct)',
        'direct',
        'null',
    ];

    /**
     * Importa un CSV histórico de eventos UTM para una empresa.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function import(Empresa $empresa, string $filePath, array $options = []): array
    {
        $result = $this->makeInitialResult($empresa, $filePath, $options);

        if (! is_file($filePath) || ! is_readable($filePath)) {
            $result['errors'][] = $this->makeError(0, null, 'file_unreadable', 'No se pudo leer el archivo CSV indicado.', 'file');

            return $result;
        }

        $property = $empresa->relationLoaded('seoProperty')
            ? $empresa->seoProperty
            : $empresa->seoProperty()->first();

        if (! $property instanceof EmpresaSeoProperty) {
            $result['errors'][] = $this->makeError(0, null, 'missing_company_seo_property', 'La empresa no tiene configuración SEO; no se puede validar propiedad del dominio.', 'empresa');

            return $result;
        }

        $companyDomains = $this->extractCompanyDomains($property);

        if ($companyDomains === []) {
            $result['errors'][] = $this->makeError(0, null, 'missing_company_domains', 'La configuración SEO de la empresa no tiene dominios válidos en site_url o wordpress_site_url.', 'empresa');

            return $result;
        }

        $delimiter = $this->detectDelimiter($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            $result['errors'][] = $this->makeError(0, null, 'file_open_failed', 'No se pudo abrir el archivo CSV para lectura.', 'file');

            return $result;
        }

        try {
            $header = $this->readCsvRow($handle, $delimiter, true);

            if ($header === null) {
                $result['errors'][] = $this->makeError(0, null, 'missing_header', 'El CSV debe incluir cabecera y al menos una fila de datos.', 'header');

                return $result;
            }

            $headerMap = $this->buildHeaderMap($header);
            $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($headerMap)));

            if ($missingColumns !== []) {
                $detectedHeaders = array_keys($headerMap);
                $result['errors'][] = $this->makeError(
                    0,
                    null,
                    'missing_columns',
                    'Faltan columnas requeridas: ' . implode(', ', $missingColumns) . '. '
                    . 'Cabeceras detectadas (normalizadas): '
                    . ($detectedHeaders !== [] ? implode(', ', $detectedHeaders) : '[ninguna]') . '.',
                    'header'
                );

                return $result;
            }

            $maxRows = $this->maxRows();
            $ownership = $this->evaluateFileOwnership($handle, $delimiter, $headerMap, $companyDomains, $maxRows);

            $result['total_rows'] = $ownership['total_rows'];
            $result['detected_domains'] = $ownership['detected_domains'];
            $result['matched_company_domain'] = $ownership['matched_company_domain'];
            $result['ownership_confidence'] = $ownership['ownership_confidence'];
            $result['file_ownership_passed'] = $ownership['file_ownership_passed'];

            if (! $ownership['file_ownership_passed']) {
                $result['errors'][] = $this->makeError(
                    0,
                    null,
                    'file_ownership_failed',
                    $ownership['reason'],
                    'file'
                );

                return $result;
            }

            if (! rewind($handle)) {
                $result['errors'][] = $this->makeError(0, null, 'file_rewind_failed', 'No se pudo reiniciar la lectura del archivo CSV para el procesamiento.', 'file');

                return $result;
            }

            $headerAgain = $this->readCsvRow($handle, $delimiter, true);

            if ($headerAgain === null) {
                $result['errors'][] = $this->makeError(0, null, 'missing_header_after_rewind', 'No se pudo volver a leer la cabecera del CSV.', 'header');

                return $result;
            }

            $chunkSize = $this->chunkSize();
            $rowNumber = 1;
            $chunk = [];

            while (($row = $this->readCsvRow($handle, $delimiter)) !== null) {
                $rowNumber++;

                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $chunk[] = [
                    'row_number' => $rowNumber,
                    'row' => $this->associateRow($headerMap, $row),
                ];

                if (count($chunk) >= $chunkSize) {
                    $this->processChunk($empresa, $chunk, $result);
                    $chunk = [];
                }
            }

            if (($ownership['total_rows'] ?? 0) === 0) {
                $result['errors'][] = $this->makeError(0, null, 'missing_data_rows', 'El CSV debe contener al menos una fila de datos.', 'file');

                return $result;
            }

            if ($chunk !== []) {
                $this->processChunk($empresa, $chunk, $result);
            }
        } finally {
            fclose($handle);
        }

        if ($result['created'] > 0 && $property->exists) {
            $property->markUtmSynced();
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @param  array<string, mixed>  $result
     */
    private function processChunk(Empresa $empresa, array $chunk, array &$result): void
    {
        foreach ($chunk as $item) {
            $rowNumber = (int) $item['row_number'];
            $csvRow = $item['row'];
            $csvId = $this->nullableScalar($csvRow['id'] ?? null);

            try {
                $normalized = $this->normalizeImportRow($csvRow);
            } catch (\InvalidArgumentException $e) {
                $result['failed']++;
                $result['errors'][] = $this->makeError(
                    $rowNumber,
                    $csvId,
                    'row_invalid',
                    $e->getMessage(),
                    $this->resolveErrorField($e->getCode())
                );

                continue;
            }

            $result['processed']++;

            foreach ($normalized['warnings'] as $warning) {
                $result['warnings'][] = $this->makeWarning(
                    $rowNumber,
                    $csvId,
                    $warning['type'],
                    $warning['message'],
                    $warning['field']
                );
            }

            $payload = $normalized['payload'];

            if ($this->findDuplicate($empresa, $payload) instanceof SeoUtmConversion) {
                $result['skipped_duplicate']++;
                continue;
            }

            SeoUtmConversion::create(array_merge($payload, [
                'empresa_id' => $empresa->id,
            ]));

            $result['created']++;
        }
    }

    /**
     * @param  array<string, mixed>  $csvRow
     * @return array{payload: array<string, mixed>, warnings: array<int, array<string, string>>}
     */
    private function normalizeImportRow(array $csvRow): array
    {
        $warnings = [];
        $extra = $this->parseExtraField($csvRow['extra'] ?? null, $warnings);
        $conversionDateTime = $this->normalizeDateTime($csvRow['created_at'] ?? null);
        $pageUrl = $this->normalizeUrl($csvRow['referrer'] ?? null, true, $warnings, 'referrer');
        $candidateDomains = $this->extractCandidateDomains($pageUrl, $extra);

        $source = $this->normalizeText($csvRow['utm_source'] ?? null, 120, true, $warnings, 'utm_source');
        $medium = $this->normalizeText($csvRow['utm_medium'] ?? null, 120, true, $warnings, 'utm_medium');
        $campaign = $this->normalizeText($csvRow['utm_campaign'] ?? null, 150, false, $warnings, 'utm_campaign');
        $term = $this->normalizeText($csvRow['utm_term'] ?? null, 150, false, $warnings, 'utm_term');
        $content = $this->normalizeText($csvRow['utm_content'] ?? null, 150, false, $warnings, 'utm_content');
        $eventName = $this->normalizeText($csvRow['event_name'] ?? $csvRow['event_type'] ?? null, 120, true, $warnings, 'event_name');
        $formName = $this->normalizeText($csvRow['form_name'] ?? null, 150, false, $warnings, 'form_name');

        if (! $this->hasMeaningfulAttribution($pageUrl, [$source, $medium, $campaign, $term, $content])) {
            throw new \InvalidArgumentException('La fila no tiene URL atribuible ni valores UTM útiles para importar.', 3);
        }

        $rawPayload = $this->buildRawPayload($csvRow, $extra, $pageUrl, $candidateDomains);

        return [
            'payload' => [
                'conversion_datetime' => $conversionDateTime->toDateTimeString(),
                'page_url' => $pageUrl,
                'form_name' => $formName,
                'source' => $source,
                'medium' => $medium,
                'campaign' => $campaign,
                'term' => $term,
                'content' => $content,
                'event_name' => $eventName,
                'lead_id' => null,
                'raw_payload_json' => $rawPayload,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array<string, int>  $headerMap
     * @param  array<int, string>  $companyDomains
     * @return array{
     *   total_rows: int,
     *   detected_domains: array<int, array{domain: string, count: int, external: bool, matches_company: bool}>,
     *   matched_company_domain: string|null,
     *   ownership_confidence: float,
     *   file_ownership_passed: bool,
     *   reason: string
     * }
     */
    private function evaluateFileOwnership($handle, string $delimiter, array $headerMap, array $companyDomains, int $maxRows): array
    {
        $domainCounts = [];
        $rowCount = 0;

        while (($row = $this->readCsvRow($handle, $delimiter)) !== null) {
            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $rowCount++;

            if ($rowCount > $maxRows) {
                return [
                    'total_rows' => $rowCount,
                    'detected_domains' => [],
                    'matched_company_domain' => null,
                    'ownership_confidence' => 0.0,
                    'file_ownership_passed' => false,
                    'reason' => 'El archivo excede el máximo permitido de ' . $maxRows . ' filas.',
                ];
            }

            $associated = $this->associateRow($headerMap, $row);
            $domainsInRow = $this->extractOwnershipDomainsFromRow($associated);

            foreach ($domainsInRow as $domain) {
                if (! isset($domainCounts[$domain])) {
                    $domainCounts[$domain] = 0;
                }

                $domainCounts[$domain]++;
            }
        }

        if ($rowCount === 0) {
            return [
                'total_rows' => 0,
                'detected_domains' => [],
                'matched_company_domain' => null,
                'ownership_confidence' => 0.0,
                'file_ownership_passed' => false,
                'reason' => 'El CSV debe contener al menos una fila de datos.',
            ];
        }

        arsort($domainCounts);

        $companySignals = 0;
        $nonExternalSignals = 0;
        $matchedCompanyCounts = [];
        $detectedDomains = [];

        foreach ($domainCounts as $domain => $count) {
            $isExternal = $this->isExternalReferenceDomain($domain);
            $matchedCompany = $this->resolveMatchedCompanyDomain($domain, $companyDomains);

            if ($matchedCompany !== null) {
                $companySignals += $count;
                if (! isset($matchedCompanyCounts[$matchedCompany])) {
                    $matchedCompanyCounts[$matchedCompany] = 0;
                }
                $matchedCompanyCounts[$matchedCompany] += $count;
            }

            if (! $isExternal) {
                $nonExternalSignals += $count;
            }

            $detectedDomains[] = [
                'domain' => $domain,
                'count' => (int) $count,
                'external' => $isExternal,
                'matches_company' => $matchedCompany !== null,
            ];
        }

        $ownershipConfidence = $nonExternalSignals > 0
            ? round($companySignals / $nonExternalSignals, 4)
            : ($companySignals > 0 ? 1.0 : 0.0);

        arsort($matchedCompanyCounts);
        $matchedCompanyDomain = $matchedCompanyCounts === []
            ? null
            : (string) array_key_first($matchedCompanyCounts);

        $minimumMatches = $this->ownershipMinimumMatches();
        $minimumConfidence = $this->ownershipMinimumConfidence();

        $passed = false;

        if ($companySignals > 0) {
            if ($nonExternalSignals === 0) {
                $passed = $companySignals >= $minimumMatches;
            } else {
                $passed = $companySignals >= $minimumMatches
                    && $ownershipConfidence >= $minimumConfidence;
            }
        }

        if (! $passed) {
            $reason = 'No se pudo confirmar que el archivo pertenezca a la empresa seleccionada. '
                . 'Coincidencias de dominio empresa: ' . $companySignals
                . ', señales no externas: ' . $nonExternalSignals
                . ', confianza: ' . $ownershipConfidence . '.';
        } else {
            $reason = 'Archivo validado por ownership a nivel de archivo.';
        }

        return [
            'total_rows' => $rowCount,
            'detected_domains' => $detectedDomains,
            'matched_company_domain' => $matchedCompanyDomain,
            'ownership_confidence' => $ownershipConfidence,
            'file_ownership_passed' => $passed,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function extractOwnershipDomainsFromRow(array $row): array
    {
        $warnings = [];
        $extra = $this->parseExtraField($row['extra'] ?? null, $warnings);
        $domains = [];

        foreach ([
            $row['referrer'] ?? null,
            $extra['finalUrl'] ?? null,
            $extra['target'] ?? null,
        ] as $value) {
            $domain = $this->extractDomainFromUrl($value);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findDuplicate(Empresa $empresa, array $payload): ?SeoUtmConversion
    {
        $timestamp = Carbon::parse($payload['conversion_datetime'], 'UTC');
        $window = $this->duplicateWindowSeconds();

        $query = SeoUtmConversion::query()
            ->where('empresa_id', $empresa->id)
            ->whereBetween('conversion_datetime', [
                $timestamp->copy()->subSeconds($window)->toDateTimeString(),
                $timestamp->copy()->addSeconds($window)->toDateTimeString(),
            ]);

        $this->applyNullSafeWhere($query, 'page_url', $payload['page_url']);
        $this->applyNullSafeWhere($query, 'source', $payload['source']);
        $this->applyNullSafeWhere($query, 'medium', $payload['medium']);
        $this->applyNullSafeWhere($query, 'campaign', $payload['campaign']);
        $this->applyNullSafeWhere($query, 'term', $payload['term']);
        $this->applyNullSafeWhere($query, 'content', $payload['content']);
        $this->applyNullSafeWhere($query, 'event_name', $payload['event_name']);

        return $query->first();
    }

    /**
     * @param  mixed  $value
     * @param  array<int, array<string, string>>  $warnings
     */
    private function normalizeText($value, int $maxLength, bool $lowercase, array &$warnings, string $field): ?string
    {
        $normalized = $this->sanitizeScalar($value);

        if ($normalized === null) {
            return null;
        }

        if ($lowercase) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        }

        if (mb_strlen($normalized, 'UTF-8') > $maxLength) {
            $warnings[] = [
                'type' => 'string_truncated',
                'message' => $field . ' excede la longitud máxima y fue truncado a ' . $maxLength . ' caracteres.',
                'field' => $field,
            ];

            $normalized = mb_substr($normalized, 0, $maxLength, 'UTF-8');
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @param  array<int, array<string, string>>  $warnings
     */
    private function normalizeUrl($value, bool $dropDirectTokens, array &$warnings, string $field): ?string
    {
        $scalar = $this->sanitizeScalar($value, $dropDirectTokens);

        if ($scalar === null) {
            return null;
        }

        if (! filter_var($scalar, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($scalar);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $host = strtolower($parts['host']);
        $url = $scheme . '://' . $host;

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $url .= $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }

        if (mb_strlen($url, 'UTF-8') > 500) {
            $warnings[] = [
                'type' => 'string_truncated',
                'message' => $field . ' excede la longitud máxima y fue truncado a 500 caracteres.',
                'field' => $field,
            ];

            $url = mb_substr($url, 0, 500, 'UTF-8');
        }

        return $url;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeDateTime($value): Carbon
    {
        $scalar = $this->sanitizeScalar($value, false);

        if ($scalar === null) {
            throw new \InvalidArgumentException('created_at es obligatorio y debe contener una fecha válida.', 1);
        }

        try {
            return Carbon::parse($scalar, 'UTC')->utc();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('created_at no es una fecha válida o no se pudo convertir a UTC.', 1);
        }
    }

    /**
     * @param  mixed  $value
     * @param  array<int, array<string, string>>  $warnings
     * @return array<string, mixed>
     */
    private function parseExtraField($value, array &$warnings): array
    {
        if (is_array($value)) {
            return $value;
        }

        $scalar = $this->sanitizeScalar($value, false);

        if ($scalar === null) {
            return [];
        }

        $decoded = json_decode($scalar, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $warnings[] = [
                'type' => 'invalid_extra_json',
                'message' => 'La columna extra no contiene JSON válido; se ignoró para la resolución de URLs.',
                'field' => 'extra',
            ];

            return [];
        }

        return $decoded;
    }

    /**
     * @param  array<int, string>  $companyDomains
     * @param  array<int, string>  $candidateDomains
     */
    private function matchesCompanyDomains(array $candidateDomains, array $companyDomains): bool
    {
        if ($candidateDomains === []) {
            return false;
        }

        foreach ($candidateDomains as $candidateDomain) {
            foreach ($companyDomains as $companyDomain) {
                if ($candidateDomain === $companyDomain) {
                    return true;
                }

                $suffix = '.' . $companyDomain;
                if (substr($candidateDomain, -strlen($suffix)) === $suffix) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveMatchedCompanyDomain(string $candidateDomain, array $companyDomains): ?string
    {
        foreach ($companyDomains as $companyDomain) {
            if ($candidateDomain === $companyDomain) {
                return $companyDomain;
            }

            $suffix = '.' . $companyDomain;
            if (substr($candidateDomain, -strlen($suffix)) === $suffix) {
                return $companyDomain;
            }
        }

        return null;
    }

    private function isExternalReferenceDomain(string $domain): bool
    {
        foreach (self::EXTERNAL_REFERENCE_DOMAINS as $externalDomain) {
            if ($domain === $externalDomain || substr($domain, -strlen('.' . $externalDomain)) === '.' . $externalDomain) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $utmFields
     */
    private function hasMeaningfulAttribution(?string $pageUrl, array $utmFields): bool
    {
        if ($pageUrl !== null) {
            return true;
        }

        foreach ($utmFields as $field) {
            if ($field !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    private function extractCandidateDomains(?string $pageUrl, array $extra): array
    {
        $domains = [];

        foreach ([$pageUrl, $extra['finalUrl'] ?? null, $extra['target'] ?? null] as $value) {
            $domain = $this->extractDomainFromUrl($value);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @return array<int, string>
     */
    private function extractCompanyDomains(EmpresaSeoProperty $property): array
    {
        $domains = [];

        foreach ([$property->site_url, $property->wordpress_site_url] as $url) {
            $domain = $this->extractDomainFromUrl($url);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @param  mixed  $value
     */
    private function extractDomainFromUrl($value): ?string
    {
        $scalar = $this->sanitizeScalar($value, false);

        if ($scalar === null || ! filter_var($scalar, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = parse_url($scalar, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return $this->normalizeDomain($host);
    }

    private function normalizeDomain(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host);

        return rtrim((string) $host, '.');
    }

    /**
     * @param  mixed  $value
     */
    private function sanitizeScalar($value, bool $dropDirectTokens = true): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return null;
        }

        $string = trim(str_replace("\0", '', (string) $value));

        if ($string === '') {
            return null;
        }

        $token = mb_strtolower($string, 'UTF-8');

        if (in_array($token, self::EMPTY_TOKENS, true)) {
            if ($token === 'direct' || $token === '(direct)') {
                return $dropDirectTokens ? null : $string;
            }

            return null;
        }

        return $string;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function associateRow(array $headerMap, array $row): array
    {
        $knownColumns = array_merge(self::REQUIRED_COLUMNS, self::OPTIONAL_COLUMNS);
        $associated = [];

        foreach ($knownColumns as $column) {
            $index = $headerMap[$column] ?? null;
            $associated[$column] = $index === null ? null : ($row[$index] ?? null);
        }

        return $associated;
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private function buildHeaderMap(array $header): array
    {
        $map = [];

        foreach ($header as $index => $value) {
            $column = $this->normalizeHeaderName($value);

            if ($column !== null && ! array_key_exists($column, $map)) {
                $map[$column] = (int) $index;
            }
        }

        return $map;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeHeaderName($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $header = $this->convertToUtf8((string) $value);
        $header = $this->stripLeadingBom($header);
        $header = trim($header);

        if ($header === '') {
            return null;
        }

        return strtolower($header);
    }

    private function detectDelimiter(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return ',';
        }

        $commaScore = 0;
        $semicolonScore = 0;
        $linesRead = 0;

        try {
            while (($line = fgets($handle)) !== false && $linesRead < 5) {
                $line = $this->convertToUtf8($line);
                $line = $this->stripLeadingBom($line);

                if (trim($line) === '') {
                    continue;
                }

                $commaScore += substr_count($line, ',');
                $semicolonScore += substr_count($line, ';');
                $linesRead++;
            }
        } finally {
            fclose($handle);
        }

        return $semicolonScore > $commaScore ? ';' : ',';
    }

    /**
     * @param  resource  $handle
     * @return array<int, mixed>|null
     */
    private function readCsvRow($handle, string $delimiter, bool $isHeader = false): ?array
    {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $converted = [];

            foreach ($row as $index => $value) {
                $cell = is_string($value) ? $this->convertToUtf8($value) : $value;
                if ($isHeader && $index === 0 && is_string($cell)) {
                    $cell = $this->stripLeadingBom($cell);
                }
                $converted[] = $cell;
            }

            return $converted;
        }

        return null;
    }

    private function convertToUtf8(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

            if ($encoding !== false && $encoding !== 'UTF-8') {
                return mb_convert_encoding($value, 'UTF-8', $encoding);
            }
        }

        return $value;
    }

    private function stripLeadingBom(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // BOM UTF-8 real (bytes EF BB BF)
        if (strpos($value, "\xEF\xBB\xBF") === 0) {
            $value = substr($value, 3);
        }

        // BOM como caracter Unicode U+FEFF ya decodificado
        $value = preg_replace('/^\x{FEFF}/u', '', $value);

        // BOM mojibake común cuando se interpreta mal el UTF-8
        if (strpos($value, 'ï»¿') === 0) {
            $value = substr($value, 6);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $extra
     * @param  array<int, string>  $candidateDomains
     * @return array<string, mixed>
     */
    private function buildRawPayload(array $row, array $extra, ?string $pageUrl, array $candidateDomains): array
    {
        return [
            'csv_row' => $row,
            'normalized' => [
                'page_url' => $pageUrl,
                'candidate_domains' => $candidateDomains,
            ],
            'extra_json' => $extra,
            'imported_at' => now()->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $value
     * @return string|int|null
     */
    private function nullableScalar($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function makeInitialResult(Empresa $empresa, string $filePath, array $options): array
    {
        return [
            'total_rows' => 0,
            'processed' => 0,
            'created' => 0,
            'skipped_duplicate' => 0,
            'failed' => 0,
            'errors' => [],
            'warnings' => [],
            'detected_domains' => [],
            'matched_company_domain' => null,
            'ownership_confidence' => 0.0,
            'file_ownership_passed' => false,
            'file_info' => [
                'filename' => isset($options['filename']) && is_string($options['filename'])
                    ? $options['filename']
                    : basename($filePath),
                'encoded_as' => 'UTF-8',
                'imported_at' => now()->utc()->toIso8601String(),
                'empresa_id' => (int) $empresa->id,
                'empresa_name' => $empresa->nombre,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeError(int $row, $csvId, string $type, string $message, string $field): array
    {
        return [
            'row' => $row,
            'csv_id' => $csvId,
            'error_type' => $type,
            'message' => $message,
            'field' => $field,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeWarning(int $row, $csvId, string $type, string $message, string $field): array
    {
        return [
            'row' => $row,
            'csv_id' => $csvId,
            'warning_type' => $type,
            'message' => $message,
            'field' => $field,
        ];
    }

    /**
     * @param  mixed  $value
     */
    private function applyNullSafeWhere($query, string $column, $value): void
    {
        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->sanitizeScalar($value, false) !== null) {
                return false;
            }
        }

        return true;
    }

    private function maxRows(): int
    {
        return (int) config('seo.csv_utm_import.max_file_rows', 10000);
    }

    private function chunkSize(): int
    {
        $size = (int) config('seo.csv_utm_import.chunk_size', 250);

        if ($size < 100) {
            return 100;
        }

        if ($size > 500) {
            return 500;
        }

        return $size;
    }

    private function duplicateWindowSeconds(): int
    {
        $window = (int) config('seo.csv_utm_import.duplicate_time_window_seconds', 60);

        return $window > 0 ? $window : 60;
    }

    private function ownershipMinimumMatches(): int
    {
        $value = (int) config('seo.csv_utm_import.ownership_min_matches', 3);

        return $value > 0 ? $value : 3;
    }

    private function ownershipMinimumConfidence(): float
    {
        $value = (float) config('seo.csv_utm_import.ownership_min_confidence', 0.20);

        if ($value < 0) {
            return 0.20;
        }

        if ($value > 1) {
            return 1.0;
        }

        return $value;
    }

    private function resolveErrorField(int $code): string
    {
        switch ($code) {
            case 1:
                return 'created_at';
            case 2:
                return 'domain';
            case 3:
                return 'all';
            default:
                return 'row';
        }
    }
}
