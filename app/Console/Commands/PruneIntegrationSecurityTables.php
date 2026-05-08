<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneIntegrationSecurityTables extends Command
{
    protected $signature = 'integration-security:prune
        {--dry-run : Muestra cuantos registros se eliminarian sin borrar datos}
        {--chunk=1000 : Tamano de lote para borrado por chunks}';

    protected $description = 'Limpia nonces y logs antiguos de integraciones con politica de retencion configurable.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(100, (int) $this->option('chunk'));

        $noncesRetentionHours = max(1, (int) config('integration_security.nonces_retention_hours', 48));
        $securityLogsRetentionDays = max(1, (int) config('integration_security.security_logs_retention_days', 30));

        $noncesCutoff = now()->subHours($noncesRetentionHours);
        $logsCutoff = now()->subDays($securityLogsRetentionDays);

        $deletedNonces = $dryRun
            ? (int) DB::table('integration_request_nonces')->where('created_at', '<', $noncesCutoff)->count()
            : $this->deleteByChunks('integration_request_nonces', $noncesCutoff, $chunkSize);

        $deletedSecurityLogs = $dryRun
            ? (int) DB::table('integration_security_logs')->where('created_at', '<', $logsCutoff)->count()
            : $this->deleteByChunks('integration_security_logs', $logsCutoff, $chunkSize);

        $summary = [
            'dry_run' => $dryRun,
            'nonces_deleted' => $deletedNonces,
            'security_logs_deleted' => $deletedSecurityLogs,
            'nonces_cutoff' => $noncesCutoff->toDateTimeString(),
            'security_logs_cutoff' => $logsCutoff->toDateTimeString(),
            'executed_at' => now()->toDateTimeString(),
        ];

        Log::info('Integration security prune summary', $summary);

        $this->line('integration-security:prune ejecutado');
        $this->line('dry_run: ' . ($dryRun ? 'true' : 'false'));
        $this->line('nonces_deleted: ' . $deletedNonces);
        $this->line('security_logs_deleted: ' . $deletedSecurityLogs);
        $this->line('executed_at: ' . $summary['executed_at']);

        return 0;
    }

    private function deleteByChunks(string $table, $cutoff, int $chunkSize): int
    {
        $deleted = 0;

        do {
            $ids = DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            $batchCount = $ids->count();

            if ($batchCount === 0) {
                break;
            }

            DB::table($table)
                ->whereIn('id', $ids->all())
                ->delete();

            $deleted += $batchCount;
        } while ($batchCount === $chunkSize);

        return $deleted;
    }
}
