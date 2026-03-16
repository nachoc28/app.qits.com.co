<?php

namespace App\Console\Commands;

use App\Jobs\Seo\SyncEmpresaGa4Job;
use App\Jobs\Seo\SyncEmpresaSearchConsoleJob;
use App\Models\EmpresaSeoProperty;
use Illuminate\Console\Command;

class SeoSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:sync
                            {--source=all : all|gsc|ga4}
                            {--empresa_id= : ID de empresa a sincronizar}
                            {--from= : Fecha inicio YYYY-MM-DD}
                            {--to= : Fecha fin YYYY-MM-DD}
                            {--queue= : Cola de destino para los jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Despacha jobs de sincronización SEO por empresa (GSC y/o GA4).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $source = strtolower((string) $this->option('source'));

        if (! in_array($source, ['all', 'gsc', 'ga4'], true)) {
            $this->error('La opción --source debe ser all, gsc o ga4.');
            return 1;
        }

        $empresaId = $this->option('empresa_id');
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        $queue = $this->option('queue') ?: (string) config('seo.sync.queue_name', 'seo-sync');

        $query = EmpresaSeoProperty::query()->with('empresa');

        if ($empresaId !== null && $empresaId !== '') {
            $query->where('empresa_id', (int) $empresaId);
        }

        if ($source === 'gsc') {
            $query->where('gsc_enabled', true)->whereNotNull('search_console_property');
        }

        if ($source === 'ga4') {
            $query->where('ga4_enabled', true)->whereNotNull('ga4_property_id');
        }

        if ($source === 'all') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('gsc_enabled', true)->whereNotNull('search_console_property');
                })->orWhere(function ($sq) {
                    $sq->where('ga4_enabled', true)->whereNotNull('ga4_property_id');
                });
            });
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->warn('No hay empresas SEO candidatas para sincronizar.');
            return 0;
        }

        $gscDispatched = 0;
        $ga4Dispatched = 0;

        /** @var EmpresaSeoProperty $property */
        foreach ($properties as $property) {
            if ($source !== 'ga4' && $property->isGscReady()) {
                $job = new SyncEmpresaSearchConsoleJob($property, $from, $to);
                dispatch($job->onQueue($queue));
                $gscDispatched++;
            }

            if ($source !== 'gsc' && $property->isGa4Ready()) {
                $job = new SyncEmpresaGa4Job($property, $from, $to);
                dispatch($job->onQueue($queue));
                $ga4Dispatched++;
            }
        }

        $this->info('Despacho SEO completado.');
        $this->line('Jobs GSC: ' . $gscDispatched);
        $this->line('Jobs GA4: ' . $ga4Dispatched);

        return 0;
    }
}
