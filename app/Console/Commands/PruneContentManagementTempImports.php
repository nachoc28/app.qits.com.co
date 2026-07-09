<?php

namespace App\Console\Commands;

use App\Services\ContentManagement\ContentXlsxImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneContentManagementTempImports extends Command
{
    protected $signature = 'content-management:prune-temp-imports
        {--dry-run : Muestra cuántos archivos temporales serían eliminados sin borrarlos}
        {--older-than-minutes= : Sobrescribe la ventana mínima de antigüedad para purga}';

    protected $description = 'Elimina archivos XLSX temporales abandonados del staging de Content Management.';

    public function handle(ContentXlsxImportService $service): int
    {
        $minutes = $this->resolveMinutesThreshold();
        $disk = $service->temporaryImportDisk();
        $directory = $service->temporaryImportDirectory();

        if ((bool) $this->option('dry-run')) {
            $wouldDelete = $this->countFilesOlderThanMinutes($service, $minutes);

            $this->line('content-management:prune-temp-imports ejecutado');
            $this->line('dry_run: true');
            $this->line('temp_disk: ' . $disk);
            $this->line('temp_directory: ' . $directory);
            $this->line('older_than_minutes: ' . $minutes);
            $this->line('files_deleted: ' . $wouldDelete);

            return 0;
        }

        $deleted = $service->pruneTemporaryFilesOlderThanMinutes($minutes);

        Log::info('Content Management temp import prune summary', [
            'temp_disk' => $disk,
            'temp_directory' => $directory,
            'older_than_minutes' => $minutes,
            'files_deleted' => $deleted,
            'executed_at' => now()->toDateTimeString(),
        ]);

        $this->line('content-management:prune-temp-imports ejecutado');
        $this->line('dry_run: false');
        $this->line('temp_disk: ' . $disk);
        $this->line('temp_directory: ' . $directory);
        $this->line('older_than_minutes: ' . $minutes);
        $this->line('files_deleted: ' . $deleted);

        return 0;
    }

    private function resolveMinutesThreshold(): int
    {
        $configured = (int) config('content_management.xlsx_import.temp_file_ttl_minutes', 180);
        $option = $this->option('older-than-minutes');

        if ($option === null || $option === '') {
            return max(1, $configured);
        }

        return max(1, (int) $option);
    }

    private function countFilesOlderThanMinutes(ContentXlsxImportService $service, int $minutes): int
    {
        $disk = \Illuminate\Support\Facades\Storage::disk($service->temporaryImportDisk());
        $cutoff = now()->subMinutes($minutes)->getTimestamp();
        $count = 0;

        foreach ($disk->allFiles($service->temporaryImportDirectory()) as $path) {
            if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
                continue;
            }

            if ($disk->lastModified($path) < $cutoff) {
                $count++;
            }
        }

        return $count;
    }
}
