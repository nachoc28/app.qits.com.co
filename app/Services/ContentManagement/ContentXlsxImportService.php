<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\ContentImport;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class ContentXlsxImportService
{
    private const TEMP_DIR = 'tmp/content-management/imports';

    private const REQUIRED_HEADERS = [
        'fecha' => 'article_date',
        'tema del articulo' => 'topic',
        'objetivo estrategico' => 'strategic_objective_general',
        'publico objetivo' => 'target_audience_general',
    ];

    /**
     * Importa un archivo XLSX cargado temporalmente y lo elimina al finalizar.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importUploadedFile(Empresa $empresa, User $user, UploadedFile $uploadedFile, array $options = []): array
    {
        $preview = $this->previewUploadedFile($empresa, $uploadedFile, $options);
        $storedPath = $preview['stored_path'] ?? null;

        try {
            if (! is_string($storedPath) || $storedPath === '') {
                return $preview;
            }

            return $this->importStoredFile(
                $empresa,
                $user,
                $storedPath,
                $this->mergeResolvedFileOptions($preview, $options)
            );
        } finally {
            $this->deleteTemporaryFile($storedPath);
        }
    }

    /**
     * Guarda temporalmente un XLSX y devuelve el resultado de validaciÃ³n previo.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function previewUploadedFile(Empresa $empresa, UploadedFile $uploadedFile, array $options = []): array
    {
        $originalName = (string) $uploadedFile->getClientOriginalName();
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());

        if ($extension !== 'xlsx') {
            $result = $this->makeInitialResult($empresa, [
                'filename' => $originalName,
                'import_name' => $options['import_name'] ?? null,
            ], [
                $this->makeError(0, 'file', 'Solo se aceptan archivos .xlsx.'),
            ]);

            $result['stored_path'] = null;

            return $result;
        }

        $storedPath = $this->storeTemporaryUploadedFile($empresa, $uploadedFile);
        $preview = $this->previewStoredFile(
            $empresa,
            $storedPath,
            array_merge($options, ['filename' => $originalName])
        );

        $preview['stored_path'] = $storedPath;

        return $preview;
    }

    /**
     * Valida un XLSX ya guardado temporalmente sin persistir registros.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function previewStoredFile(Empresa $empresa, string $storedPath, array $options = []): array
    {
        return $this->validateFileAtPath(
            $empresa,
            $this->resolveStoredFileAbsolutePath($storedPath),
            $options
        );
    }

    /**
     * Importa un XLSX ya guardado temporalmente.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importStoredFile(Empresa $empresa, User $user, string $storedPath, array $options = []): array
    {
        return $this->importFromPath(
            $empresa,
            $user,
            $this->resolveStoredFileAbsolutePath($storedPath),
            $options
        );
    }

    public function deleteTemporaryFile(?string $storedPath): void
    {
        $disk = Storage::disk($this->temporaryImportDisk());

        if (is_string($storedPath) && $storedPath !== '' && $disk->exists($storedPath)) {
            $disk->delete($storedPath);
        }
    }

    public function temporaryImportDisk(): string
    {
        return (string) config('content_management.xlsx_import.temp_disk', 'local');
    }

    public function temporaryImportDirectory(): string
    {
        return self::TEMP_DIR;
    }

    public function pruneTemporaryFilesOlderThanMinutes(int $minutes): int
    {
        $minutes = max(1, $minutes);
        $cutoff = now()->subMinutes($minutes)->getTimestamp();
        $disk = Storage::disk($this->temporaryImportDisk());
        $deleted = 0;

        foreach ($disk->allFiles($this->temporaryImportDirectory()) as $path) {
            if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
                continue;
            }

            if ($disk->lastModified($path) >= $cutoff) {
                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Valida completamente el XLSX y persiste solo si todas las filas son vÃ¡lidas.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importFromPath(Empresa $empresa, User $user, string $filePath, array $options = []): array
    {
        $analysis = $this->analyzeFile($empresa, $filePath, $options);
        $result = $analysis['result'];
        $preparedRows = $analysis['prepared_rows'];
        $tone = $analysis['tone'];

        if (! $result['can_persist'] || $tone === null) {
            return $result;
        }

        try {
            DB::transaction(function () use ($empresa, $user, $preparedRows, $tone, $options, &$result) {
                $import = $this->persistPreparedRows($empresa, $user, $preparedRows, $tone, $options);

                $result['persisted'] = true;
                $result['created'] = count($preparedRows);
                $result['import_id'] = $import->id;
            });
        } catch (Throwable $e) {
            $result['persisted'] = false;
            $result['created'] = 0;
            $result['import_id'] = null;
            $result['can_persist'] = false;
            $result['errors'][] = $this->makeError(0, 'database', 'No se pudo persistir la importaciÃ³n XLSX.', 'persistence');
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $preparedRows
     * @param  array<string, mixed>  $options
     */
    protected function persistPreparedRows(
        Empresa $empresa,
        User $user,
        array $preparedRows,
        string $tone,
        array $options = []
    ): ContentImport {
        $import = $this->createContentImport([
            'empresa_id' => $empresa->id,
            'import_name' => $this->resolveImportName($options),
            'source_file_name' => $this->resolveSourceFilename($options),
            'imported_by' => $user->id,
            'imported_at' => now(),
        ]);

        foreach ($preparedRows as $prepared) {
            $article = $this->createContentArticle([
                'content_import_id' => $import->id,
                'article_date' => $prepared['article_date'],
                'topic' => $prepared['topic'],
                'strategic_objective_general' => $prepared['strategic_objective_general'],
                'target_audience_general' => $prepared['target_audience_general'],
                'refined_objective' => null,
                'refined_target_audience' => null,
                'tone' => $tone,
                'main_status' => ContentArticle::MAIN_STATUS_PROCESSING,
                'operational_stage' => ContentArticle::STAGE_PENDING,
            ]);

            foreach (ContentArticleStep::STEP_TYPES as $stepType) {
                $this->createContentArticleStep([
                    'content_article_id' => $article->id,
                    'step_type' => $stepType,
                    'step_status' => ContentArticleStep::STATUS_PENDING,
                    'ready_at' => null,
                    'ready_by' => null,
                ]);
            }
        }

        return $import;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createContentImport(array $attributes): ContentImport
    {
        return ContentImport::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createContentArticle(array $attributes): ContentArticle
    {
        return ContentArticle::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createContentArticleStep(array $attributes): ContentArticleStep
    {
        return ContentArticleStep::create($attributes);
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $value) {
            $normalized = $this->normalizeHeader($value);

            if ($normalized !== null && ! array_key_exists($normalized, $map)) {
                $map[$normalized] = (int) $index;
            }
        }

        return $map;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeHeader($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = (string) Str::of((string) $value)
            ->replace("\xEF\xBB\xBF", '')
            ->ascii()
            ->lower();

        $normalized = $this->squish($normalized);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>
     */
    private function validateRow(int $rowNumber, array $row, array $headerMap): array
    {
        $errors = [];

        $dateValue = $row[$headerMap['fecha']] ?? null;
        $topicValue = $row[$headerMap['tema del articulo']] ?? null;
        $objectiveValue = $row[$headerMap['objetivo estrategico']] ?? null;
        $audienceValue = $row[$headerMap['publico objetivo']] ?? null;

        $articleDate = $this->normalizeDate($dateValue);
        if ($articleDate === null) {
            $errors[] = $this->makeError($rowNumber, 'fecha', 'La fecha no es vÃ¡lida.');
        }

        $topic = $this->normalizeRequiredText($topicValue);
        if ($topic === null) {
            $errors[] = $this->makeError($rowNumber, 'tema_del_articulo', 'El tema del artÃ­culo es obligatorio.');
        }

        $objective = $this->normalizeRequiredText($objectiveValue);
        if ($objective === null) {
            $errors[] = $this->makeError($rowNumber, 'objetivo_estrategico', 'El objetivo estratÃ©gico es obligatorio.');
        }

        $audience = $this->normalizeRequiredText($audienceValue);
        if ($audience === null) {
            $errors[] = $this->makeError($rowNumber, 'publico_objetivo', 'El pÃºblico objetivo es obligatorio.');
        }

        return [
            'row' => $rowNumber,
            'errors' => $errors,
            'article_date' => $articleDate,
            'topic' => $topic,
            'topic_normalized' => $topic !== null ? $this->normalizeTopicForDuplicate($topic) : null,
            'strategic_objective_general' => $objective,
            'target_audience_general' => $audience,
        ];
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeRequiredText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizeTopicForDuplicate(string $topic): string
    {
        return $this->squish((string) Str::of($topic)
            ->ascii()
            ->lower());
    }

    private function squish(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function duplicateKey(string $date, string $normalizedTopic): string
    {
        return $date . '|' . $normalizedTopic;
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<string, bool>
     */
    private function loadExistingDuplicateKeys(Empresa $empresa, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $existing = ContentArticle::query()
            ->select(['content_articles.article_date', 'content_articles.topic'])
            ->join('content_imports', 'content_imports.id', '=', 'content_articles.content_import_id')
            ->where('content_imports.empresa_id', $empresa->id)
            ->whereIn('content_articles.article_date', $dates)
            ->get();

        $keys = [];

        foreach ($existing as $row) {
            $articleDate = $row->article_date instanceof Carbon
                ? $row->article_date->toDateString()
                : (string) $row->article_date;

            $keys[$this->duplicateKey(
                $articleDate,
                $this->normalizeTopicForDuplicate((string) $row->topic)
            )] = true;
        }

        return $keys;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeRequiredText($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveTone(array $options): ?string
    {
        $tone = isset($options['tone']) ? trim((string) $options['tone']) : '';

        if (! in_array($tone, ContentArticle::TONES, true)) {
            return null;
        }

        return $tone;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveImportName(array $options): string
    {
        $importName = isset($options['import_name']) ? trim((string) $options['import_name']) : '';

        if ($importName !== '') {
            return $importName;
        }

        $filename = $this->resolveSourceFilename($options);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return $base !== '' ? $base : 'Importacion XLSX';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveSourceFilename(array $options): string
    {
        $filename = isset($options['filename']) ? trim((string) $options['filename']) : '';

        return $filename !== '' ? $filename : 'content-import.xlsx';
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<string, mixed>
     */
    private function makeInitialResult(Empresa $empresa, array $options = [], array $errors = []): array
    {
        return [
            'total_rows' => 0,
            'valid_rows' => 0,
            'duplicate_rows' => 0,
            'created' => 0,
            'can_persist' => false,
            'persisted' => false,
            'import_id' => null,
            'errors' => $errors,
            'file_info' => [
                'filename' => $this->resolveSourceFilename($options),
                'import_name' => $this->resolveImportName($options),
                'empresa_id' => (int) $empresa->id,
                'empresa_name' => $empresa->nombre,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeError(int $row, string $field, string $message, string $code = 'validation'): array
    {
        return [
            'row' => $row,
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function validateFileAtPath(Empresa $empresa, string $filePath, array $options = []): array
    {
        return $this->analyzeFile($empresa, $filePath, $options)['result'];
    }

    private function storeTemporaryUploadedFile(Empresa $empresa, UploadedFile $uploadedFile): string
    {
        $filename = 'empresa-' . $empresa->id
            . '-content-' . now()->format('YmdHis')
            . '-' . Str::random(8)
            . '.xlsx';

        $storedPath = $uploadedFile->storeAs(self::TEMP_DIR, $filename, $this->temporaryImportDisk());

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('No se pudo guardar el archivo temporal XLSX.');
        }

        return $storedPath;
    }

    private function resolveStoredFileAbsolutePath(string $storedPath): string
    {
        return Storage::disk($this->temporaryImportDisk())->path(ltrim($storedPath, '/\\'));
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function mergeResolvedFileOptions(array $preview, array $options): array
    {
        $fileInfo = isset($preview['file_info']) && is_array($preview['file_info'])
            ? $preview['file_info']
            : [];

        return array_merge($options, [
            'filename' => $fileInfo['filename'] ?? ($options['filename'] ?? null),
            'import_name' => $fileInfo['import_name'] ?? ($options['import_name'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{result: array<string, mixed>, prepared_rows: array<int, array<string, mixed>>, tone: string|null}
     */
    private function analyzeFile(Empresa $empresa, string $filePath, array $options = []): array
    {
        $result = $this->makeInitialResult($empresa, $options);
        $tone = $this->resolveTone($options);

        if ($tone === null) {
            $result['errors'][] = $this->makeError(0, 'tone', 'El tono es obligatorio y debe ser tuteo o usteo.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => null,
            ];
        }

        if (! is_file($filePath) || ! is_readable($filePath)) {
            $result['errors'][] = $this->makeError(0, 'file', 'No se pudo leer el archivo XLSX indicado.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        if (strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) !== 'xlsx') {
            $result['errors'][] = $this->makeError(0, 'file', 'Solo se aceptan archivos .xlsx.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx === false) {
                $result['errors'][] = $this->makeError(0, 'file', 'El archivo debe estar en formato XLSX.');

                return [
                    'result' => $result,
                    'prepared_rows' => [],
                    'tone' => $tone,
                ];
            }

            $spreadsheet = $reader->load($filePath);
        } catch (Throwable $e) {
            $result['errors'][] = $this->makeError(0, 'file', 'No se pudo abrir el archivo XLSX.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($rows === [] || ! isset($rows[0])) {
            $result['errors'][] = $this->makeError(0, 'header', 'El archivo debe incluir encabezados y al menos una fila de datos.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        $headerMap = $this->buildHeaderMap($rows[0]);
        $missingHeaders = [];

        foreach (array_keys(self::REQUIRED_HEADERS) as $header) {
            if (! array_key_exists($header, $headerMap)) {
                $missingHeaders[] = $header;
            }
        }

        if ($missingHeaders !== []) {
            $result['errors'][] = $this->makeError(
                0,
                'header',
                'Faltan columnas requeridas: ' . implode(', ', $missingHeaders) . '.'
            );

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        $preparedRows = [];
        $seenDuplicateKeys = [];
        $datesForDuplicateLookup = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $rowNumber = $index + 1;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $result['total_rows']++;

            $prepared = $this->validateRow($rowNumber, $row, $headerMap);
            if ($prepared['errors'] !== []) {
                $result['errors'] = array_merge($result['errors'], $prepared['errors']);
                continue;
            }

            $duplicateKey = $this->duplicateKey($prepared['article_date'], $prepared['topic_normalized']);

            if (isset($seenDuplicateKeys[$duplicateKey])) {
                $result['duplicate_rows']++;
                $result['errors'][] = $this->makeError(
                    $rowNumber,
                    'tema_del_articulo',
                    'La fila duplica otra fila del mismo archivo para la misma fecha y tema.',
                    'duplicate'
                );
                continue;
            }

            $seenDuplicateKeys[$duplicateKey] = true;
            $datesForDuplicateLookup[$prepared['article_date']] = true;
            $preparedRows[] = $prepared;
        }

        if ($result['total_rows'] === 0) {
            $result['errors'][] = $this->makeError(0, 'file', 'El archivo debe contener al menos una fila de datos.');

            return [
                'result' => $result,
                'prepared_rows' => [],
                'tone' => $tone,
            ];
        }

        if ($preparedRows !== []) {
            $existingKeys = $this->loadExistingDuplicateKeys($empresa, array_keys($datesForDuplicateLookup));

            foreach ($preparedRows as $prepared) {
                $duplicateKey = $this->duplicateKey($prepared['article_date'], $prepared['topic_normalized']);

                if (isset($existingKeys[$duplicateKey])) {
                    $result['duplicate_rows']++;
                    $result['errors'][] = $this->makeError(
                        $prepared['row'],
                        'tema_del_articulo',
                        'Ya existe un artÃ­culo para la empresa con la misma fecha y tema normalizado.',
                        'duplicate'
                    );
                    continue;
                }

                $result['valid_rows']++;
            }
        }

        $result['can_persist'] = $result['errors'] === [] && $result['valid_rows'] === $result['total_rows'];

        return [
            'result' => $result,
            'prepared_rows' => $preparedRows,
            'tone' => $tone,
        ];
    }
}
