<?php

namespace App\Services\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleFile;
use App\Models\ContentArticleStep;
use App\Models\User;
use App\Support\ContentManagementLabels;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ContentFinalFileService
{
    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function availability(ContentArticle $article): array
    {
        $article->loadMissing('steps');

        $videoStep = $this->resolveStep($article, ContentArticleStep::TYPE_VIDEO_INSTAGRAM);

        if ($videoStep->step_status !== ContentArticleStep::STATUS_READY) {
            return [
                'allowed' => false,
                'message' => 'El paso ' . ContentManagementLabels::stepType(ContentArticleStep::TYPE_VIDEO_INSTAGRAM) . ' debe estar listo antes de cargar el archivo final.',
            ];
        }

        if (! in_array($article->operational_stage, [
            ContentArticle::STAGE_FINAL_FILE,
            ContentArticle::STAGE_COMPLETED,
        ], true)) {
            return [
                'allowed' => false,
                'message' => 'La etapa operativa actual no permite la carga de archivo final.',
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
    }

    public function upload(ContentArticle $article, UploadedFile $uploadedFile, User $user): ContentArticleFile
    {
        $availability = $this->availability($article);

        if (! $availability['allowed']) {
            throw new RuntimeException((string) $availability['message']);
        }

        $metadata = $this->inspectUploadedFile($uploadedFile);
        $storedPath = null;
        $disk = $this->storageDisk();

        try {
            return DB::transaction(function () use ($article, $uploadedFile, $user, $metadata, $disk, &$storedPath): ContentArticleFile {
                $lockedArticle = $this->lockArticle($article->id);
                $nextVersion = $this->nextVersionNumber($lockedArticle);
                $storedPath = $this->buildStoredPath($lockedArticle, $nextVersion, $metadata['extension']);

                $this->storeUploadedFile($disk, $storedPath, $uploadedFile);

                $file = $this->createContentArticleFile([
                    'content_article_id' => $lockedArticle->id,
                    'version_number' => $nextVersion,
                    'file_name' => $metadata['original_name'],
                    'file_path' => $storedPath,
                    'mime_type' => $metadata['mime_type'],
                    'file_size' => $metadata['file_size'],
                    'uploaded_by' => $user->id,
                    'uploaded_at' => now(),
                ]);

                $lockedArticle->forceFill([
                    'main_status' => ContentArticle::MAIN_STATUS_UNPUBLISHED,
                    'operational_stage' => ContentArticle::STAGE_COMPLETED,
                    'delivered_at' => null,
                    'published_at' => null,
                ])->save();

                return $file->fresh(['uploadedBy']);
            });
        } catch (\Throwable $e) {
            if (is_string($storedPath) && $storedPath !== '') {
                $this->deleteStoredFile($disk, $storedPath);
            }

            throw $e;
        }
    }

    public function downloadResponse(ContentArticleFile $file): StreamedResponse
    {
        $disk = $this->storageDisk();
        $stream = Storage::disk($disk)->readStream($file->file_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo abrir el archivo final solicitado.');
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $file->file_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function storageDisk(): string
    {
        return (string) config('content_management.final_files.disk', 'local');
    }

    public function baseDirectory(): string
    {
        return trim((string) config('content_management.final_files.base_dir', 'content-management/final-files'), '/');
    }

    public function maxFileSizeKilobytes(): int
    {
        return max(1, (int) config('content_management.final_files.max_file_kb', 10240));
    }

    protected function createContentArticleFile(array $attributes): ContentArticleFile
    {
        return ContentArticleFile::create($attributes);
    }

    protected function storeUploadedFile(string $disk, string $storedPath, UploadedFile $uploadedFile): void
    {
        $stream = fopen($uploadedFile->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer el archivo final cargado.');
        }

        $stored = Storage::disk($disk)->put($storedPath, $stream);
        fclose($stream);

        if (! $stored) {
            throw new RuntimeException('No se pudo almacenar el archivo final.');
        }
    }

    protected function deleteStoredFile(string $disk, string $storedPath): void
    {
        if (Storage::disk($disk)->exists($storedPath)) {
            Storage::disk($disk)->delete($storedPath);
        }
    }

    private function inspectUploadedFile(UploadedFile $uploadedFile): array
    {
        $realPath = $uploadedFile->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            throw new RuntimeException('El archivo final cargado no es valido.');
        }

        $originalName = basename((string) $uploadedFile->getClientOriginalName());
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['docx', 'pdf'], true)) {
            throw new RuntimeException('Solo se aceptan archivos DOCX o PDF.');
        }

        $fileSize = filesize($realPath);

        if (! is_int($fileSize) || $fileSize <= 0) {
            throw new RuntimeException('El archivo final no puede estar vacio.');
        }

        if ($fileSize > ($this->maxFileSizeKilobytes() * 1024)) {
            throw new RuntimeException('El archivo final supera el tamaño maximo permitido.');
        }

        $mimeType = strtolower((string) $uploadedFile->getMimeType());

        if ($extension === 'pdf') {
            $this->assertValidPdf($realPath, $mimeType);

            return [
                'original_name' => $originalName,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => $fileSize,
            ];
        }

        $this->assertValidDocx($realPath, $mimeType);

        return [
            'original_name' => $originalName,
            'extension' => 'docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => $fileSize,
        ];
    }

    private function assertValidPdf(string $realPath, string $mimeType): void
    {
        $header = file_get_contents($realPath, false, null, 0, 5);

        if ($header !== '%PDF-') {
            throw new RuntimeException('El archivo PDF no tiene una firma valida.');
        }

        if (! in_array($mimeType, ['application/pdf', 'application/x-pdf'], true)) {
            throw new RuntimeException('El MIME del archivo PDF no es coherente.');
        }
    }

    private function assertValidDocx(string $realPath, string $mimeType): void
    {
        if (! in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ], true)) {
            throw new RuntimeException('El MIME del archivo DOCX no es coherente.');
        }

        $zip = new ZipArchive();

        if ($zip->open($realPath) !== true) {
            throw new RuntimeException('El archivo DOCX no tiene una estructura valida.');
        }

        $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
        $hasWordDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $hasContentTypes || ! $hasWordDocument) {
            throw new RuntimeException('El archivo DOCX no tiene una estructura valida.');
        }
    }

    private function lockArticle(int $articleId): ContentArticle
    {
        return ContentArticle::query()
            ->whereKey($articleId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function nextVersionNumber(ContentArticle $article): int
    {
        $currentMax = ContentArticleFile::query()
            ->where('content_article_id', $article->id)
            ->max('version_number');

        return ((int) $currentMax) + 1;
    }

    private function buildStoredPath(ContentArticle $article, int $versionNumber, string $extension): string
    {
        return $this->baseDirectory()
            . '/article_' . $article->id
            . '/v' . $versionNumber
            . '_' . now()->format('YmdHis')
            . '_' . Str::random(12)
            . '.' . $extension;
    }

    private function resolveStep(ContentArticle $article, string $stepType): ContentArticleStep
    {
        $step = $article->relationLoaded('steps')
            ? $article->steps->firstWhere('step_type', $stepType)
            : $article->steps()->where('step_type', $stepType)->first();

        if (! $step instanceof ContentArticleStep) {
            throw new RuntimeException(sprintf('Step [%s] is missing for the content article.', $stepType));
        }

        return $step;
    }
}
