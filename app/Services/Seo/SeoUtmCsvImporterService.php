<?php

namespace App\Services\Seo;

use App\Models\Empresa;
use App\Models\SeoUtmConversion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class SeoUtmCsvImporterService
{
    private const SOURCE_SYSTEM = 'wordpress_utm_tracker';

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
     * Importa un CSV historico de eventos UTM para una empresa.
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
            $chunkSize = $this->chunkSize();
            $rowNumber = 1;
            $chunk = [];

            while (($row = $this->readCsvRow($handle, $delimiter)) !== null) {
                $rowNumber++;

                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $result['total_rows']++;

                if ($result['total_rows'] > $maxRows) {
                    $result['errors'][] = $this->makeError(
                        $rowNumber,
                        null,
                        'max_rows_exceeded',
                        'El archivo excede el maximo permitido de ' . $maxRows . ' filas.',
                        'file'
                    );

                    return $result;
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

            if ($result['total_rows'] === 0) {
                $result['errors'][] = $this->makeError(0, null, 'missing_data_rows', 'El CSV debe contener al menos una fila de datos.', 'file');

                return $result;
            }

            if ($chunk !== []) {
                $this->processChunk($empresa, $chunk, $result);
            }
        } finally {
            fclose($handle);
        }

        if ($result['created'] > 0) {
            $property = $empresa->relationLoaded('seoProperty')
                ? $empresa->seoProperty
                : $empresa->seoProperty()->first();

            if ($property) {
                $property->markUtmSynced();
            }
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

            try {
                SeoUtmConversion::create(array_merge($payload, [
                    'empresa_id' => $empresa->id,
                ]));

                $result['created']++;
            } catch (QueryException $e) {
                if ($this->isDuplicateKeyException($e)) {
                    $result['duplicates']++;
                    $result['skipped_duplicate']++;
                    continue;
                }

                $result['failed']++;
                $result['errors'][] = $this->makeError(
                    $rowNumber,
                    $csvId,
                    'db_error',
                    'No se pudo persistir la fila por un error de base de datos.',
                    'database'
                );
            }
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

        $sourceRecordId = $this->normalizeSourceRecordId($csvRow['id'] ?? null);
        $source = $this->normalizeText($csvRow['utm_source'] ?? null, 120, true, $warnings, 'utm_source');
        $medium = $this->normalizeText($csvRow['utm_medium'] ?? null, 120, true, $warnings, 'utm_medium');
        $campaign = $this->normalizeText($csvRow['utm_campaign'] ?? null, 150, false, $warnings, 'utm_campaign');
        $term = $this->normalizeText($csvRow['utm_term'] ?? null, 150, false, $warnings, 'utm_term');
        $content = $this->normalizeText($csvRow['utm_content'] ?? null, 150, false, $warnings, 'utm_content');
        $eventName = $this->normalizeText($csvRow['event_name'] ?? $csvRow['event_type'] ?? null, 120, true, $warnings, 'event_name');
        $formName = $this->normalizeText($csvRow['form_name'] ?? null, 150, false, $warnings, 'form_name');

        if (! $this->hasMeaningfulAttribution($pageUrl, [$source, $medium, $campaign, $term, $content])) {
            throw new \InvalidArgumentException('La fila no tiene URL atribuible ni valores UTM utiles para importar.', 3);
        }

        $rawPayload = $this->buildRawPayload($csvRow, $extra, $pageUrl);

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
                'source_system' => self::SOURCE_SYSTEM,
                'source_record_id' => $sourceRecordId,
                'raw_payload_json' => $rawPayload,
            ],
            'warnings' => $warnings,
        ];
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
                'message' => $field . ' excede la longitud maxima y fue truncado a ' . $maxLength . ' caracteres.',
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
                'message' => $field . ' excede la longitud maxima y fue truncado a 500 caracteres.',
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
            throw new \InvalidArgumentException('created_at es obligatorio y debe contener una fecha valida.', 1);
        }

        try {
            return Carbon::parse($scalar, 'UTC')->utc();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('created_at no es una fecha valida o no se pudo convertir a UTC.', 1);
        }
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeSourceRecordId($value): string
    {
        $scalar = $this->sanitizeScalar($value, false);

        if ($scalar === null) {
            throw new \InvalidArgumentException('id es obligatorio para construir source_record_id.', 4);
        }

        if (mb_strlen($scalar, 'UTF-8') > 191) {
            throw new \InvalidArgumentException('id excede la longitud maxima permitida (191).', 4);
        }

        return $scalar;
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
                'message' => 'La columna extra no contiene JSON valido; se ignoro para datos auxiliares.',
                'field' => 'extra',
            ];

            return [];
        }

        return $decoded;
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

        if (strpos($value, "\xEF\xBB\xBF") === 0) {
            $value = substr($value, 3);
        }

        $value = preg_replace('/^\x{FEFF}/u', '', $value);

        if (strpos($value, 'ï»¿') === 0) {
            $value = substr($value, 6);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildRawPayload(array $row, array $extra, ?string $pageUrl): array
    {
        return [
            'csv_row' => $row,
            'normalized' => [
                'page_url' => $pageUrl,
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

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        if (is_array($errorInfo) && isset($errorInfo[1]) && (int) $errorInfo[1] === 1062) {
            return true;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return strpos($message, 'duplicate entry') !== false;
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
            'duplicates' => 0,
            'skipped_duplicate' => 0,
            'failed' => 0,
            'errors' => [],
            'warnings' => [],
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

    private function resolveErrorField(int $code): string
    {
        switch ($code) {
            case 1:
                return 'created_at';
            case 3:
                return 'all';
            case 4:
                return 'id';
            default:
                return 'row';
        }
    }
}
