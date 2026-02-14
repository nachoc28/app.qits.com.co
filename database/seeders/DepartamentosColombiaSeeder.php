<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartamentosColombiaSeeder extends Seeder
{
    public function run(): void
    {
        // País Colombia (crea si no existe)
        $paisId = DB::table('paises')->where('nombre', 'Colombia')->value('id');
        if (!$paisId) {
            $paisId = DB::table('paises')->insertGetId([
                'nombre' => 'Colombia', 'created_at' => now(), 'updated_at' => now()
            ]);
        }

        // "Indefinido"
        DB::table('departamentos')->updateOrInsert(
            ['nombre' => 'Indefinido', 'pais_id' => $paisId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // CSV oficial DIVIPOLA exportado (codigo_depto, nombre)
        $path = database_path('seeders/data/co_departamentos.csv');
        if (!file_exists($path)) return;

        $rows = array_map('str_getcsv', file($path));
        $header = array_map('mb_strtolower', array_map('trim', array_shift($rows)));
        $cNombre = array_search('nombre', $header);

        $toUpsert = [];
        foreach ($rows as $r) {
            $nombre = trim($r[$cNombre] ?? '');
            if ($nombre === '' || mb_strtolower($nombre) === 'indefinido') continue;

            $toUpsert[] = [
                'nombre'     => $nombre,
                'pais_id'    => $paisId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($toUpsert) {
            // evita duplicados (unique pais_id+nombre en tu migración)
            DB::table('departamentos')->upsert($toUpsert, ['pais_id', 'nombre']);
        }
    }
}
