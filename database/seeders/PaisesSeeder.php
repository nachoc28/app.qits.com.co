<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaisesSeeder extends Seeder
{
    public function run(): void
    {
        // 1) "Indefinido" (id propio autoincremental; code ZZ reservado de facto para 'Unknown')
        DB::table('paises')->updateOrInsert(
            ['nombre' => 'Indefinido'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // 2) Carga masiva desde CSV ISO 3166-1
        $path = database_path('seeders/data/countries.csv'); // columns: Name,Code (alpha2)
        if (!file_exists($path)) return;

        $rows = array_map('str_getcsv', file($path));
        $header = array_map('trim', array_shift($rows));
        $colName = array_search('Name', $header);
        $colCode = array_search('Code', $header);

        $toUpsert = [];
        foreach ($rows as $r) {
            $name = trim($r[$colName] ?? '');
            $code2 = strtoupper(trim($r[$colCode] ?? ''));
            if ($name === '' || $code2 === '') continue;

            $toUpsert[] = [
                'nombre'     => $name,
                // si quieres guardar código, agrega columna en la migración (p.ej. 'iso2')
                // 'iso2'    => $code2,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // upsert por nombre (único)
        if ($toUpsert) {
            DB::table('paises')->upsert($toUpsert, ['nombre']);
        }
    }
}
