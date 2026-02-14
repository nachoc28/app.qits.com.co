<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeoImport extends Command
{
    protected $signature = 'geo:import
        {--countries : Importar países (ISO2)}
        {--colombia  : Importar departamentos y municipios DIVIPOLA}
        {--path= : Ruta base de CSVs (default: database/seeders/data)}';

    protected $description = 'Importa/actualiza países (ISO2) y Colombia (DIVIPOLA) con upsert e inserta "Indefinido".';

    public function handle(): int
    {
        $basePath = $this->option('path') ?: database_path('seeders/data');

        if ($this->option('countries')) $this->importCountries("$basePath/countries.csv");
        if ($this->option('colombia'))  $this->importColombia("$basePath/co_departamentos.csv", "$basePath/co_municipios.csv");

        if (!$this->option('countries') && !$this->option('colombia')) {
            $this->importCountries("$basePath/countries.csv");
            $this->importColombia("$basePath/co_departamentos.csv", "$basePath/co_municipios.csv");
        }

        $this->info('✅ geo:import completado.');
        return self::SUCCESS;
    }

    private function importCountries(string $file): void
    {
        if (!file_exists($file)) { $this->warn("No existe $file"); return; }

        $this->info('Importando países...');
        [$header, $rows] = $this->readCsv($file);
        $iso2 = array_search('iso2', $header);
        $name = array_search('name', $header);

        // Indefinido
        DB::table('paises')->updateOrInsert(['nombre'=>'Indefinido'], ['iso2'=>null,'created_at'=>now(),'updated_at'=>now()]);

        $batch = [];
        foreach ($rows as $r) {
            $i2 = strtoupper(trim($r[$iso2] ?? ''));
            $nm = trim($r[$name] ?? '');
            if ($i2==='' || $nm==='') continue;

            $batch[] = ['iso2'=>$i2,'nombre'=>$nm,'created_at'=>now(),'updated_at'=>now()];
            if (count($batch)>=1000){ DB::table('paises')->upsert($batch,['iso2'],['nombre','updated_at']); $batch=[]; }
        }
        if ($batch) DB::table('paises')->upsert($batch,['iso2'],['nombre','updated_at']);
        $this->info('Países OK.');
    }

    private function importColombia(string $deptFile, string $cityFile): void
    {
        // País CO
        $coId = DB::table('paises')->where('iso2','CO')->value('id')
            ?: DB::table('paises')->insertGetId(['iso2'=>'CO','nombre'=>'Colombia','created_at'=>now(),'updated_at'=>now()]);

        // Departamentos
        if (!file_exists($deptFile)) { $this->warn("No existe $deptFile"); return; }
        $this->info('Importando departamentos...');
        [$hD,$rD] = $this->readCsv($deptFile);
        $dCod = array_search('codigo_divipola',$hD);
        $dNom = array_search('nombre',$hD);

        // Indefinido
        DB::table('departamentos')->updateOrInsert(
            ['pais_id'=>$coId,'nombre'=>'Indefinido'],
            ['codigo_divipola'=>null,'created_at'=>now(),'updated_at'=>now()]
        );

        $batch = [];
        foreach ($rD as $r) {
            $cod = trim($r[$dCod] ?? '');
            $nom = trim($r[$dNom] ?? '');
            if ($nom==='' || mb_strtolower($nom)==='indefinido') continue;

            $batch[] = ['codigo_divipola'=>$cod?:null,'nombre'=>$nom,'pais_id'=>$coId,'created_at'=>now(),'updated_at'=>now()];
            if (count($batch)>=1000){ $this->upsertDept($batch); $batch=[]; }
        }
        if ($batch) $this->upsertDept($batch);

        // Map cod_depto -> id
        $deptMap = DB::table('departamentos')->where('pais_id',$coId)->whereNotNull('codigo_divipola')->pluck('id','codigo_divipola')->toArray();

        // Ciudades
        if (!file_exists($cityFile)) { $this->warn("No existe $cityFile"); return; }
        $this->info('Importando municipios...');
        [$hC,$rC] = $this->readCsv($cityFile);
        $cCod = array_search('codigo_divipola',$hC);
        $cNom = array_search('nombre',$hC);
        $cDep = array_search('codigo_departamento',$hC);

        // Indefinido por depto
        $deptIds = DB::table('departamentos')->where('pais_id',$coId)->pluck('id');
        foreach ($deptIds as $did) {
            DB::table('ciudades')->updateOrInsert(
                ['departamento_id'=>$did,'nombre'=>'Indefinido'],
                ['codigo_divipola'=>null,'created_at'=>now(),'updated_at'=>now()]
            );
        }

        $batch = [];
        foreach ($rC as $r) {
            $cod = trim($r[$cCod] ?? '');
            $nom = trim($r[$cNom] ?? '');
            $dep = trim($r[$cDep] ?? '');
            if ($nom==='' || mb_strtolower($nom)==='indefinido') continue;
            $deptId = $deptMap[$dep] ?? null;
            if (!$deptId) continue;

            $batch[] = ['codigo_divipola'=>$cod?:null,'nombre'=>$nom,'departamento_id'=>$deptId,'created_at'=>now(),'updated_at'=>now()];
            if (count($batch)>=1000){ $this->upsertCity($batch); $batch=[]; }
        }
        if ($batch) $this->upsertCity($batch);

        $this->info('Colombia OK.');
    }

    private function upsertDept(array $rows): void
    {
        $has = collect($rows)->contains(fn($r)=>!empty($r['codigo_divipola']));
        if ($has) DB::table('departamentos')->upsert($rows,['codigo_divipola'],['nombre','pais_id','updated_at']);
        else      DB::table('departamentos')->upsert($rows,['pais_id','nombre'],['updated_at']);
    }

    private function upsertCity(array $rows): void
    {
        $has = collect($rows)->contains(fn($r)=>!empty($r['codigo_divipola']));
        if ($has) DB::table('ciudades')->upsert($rows,['codigo_divipola'],['nombre','departamento_id','updated_at']);
        else      DB::table('ciudades')->upsert($rows,['departamento_id','nombre'],['updated_at']);
    }

    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = array_map(fn($h)=>mb_strtolower(trim($h)), fgetcsv($fh, 0, ','));
        $rows = [];
        while (($data = fgetcsv($fh, 0, ',')) !== false) $rows[] = $data;
        fclose($fh);
        return [$header, $rows];
    }
}
