<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CiudadesColombiaSeeder extends Seeder
{
    public function run(): void
    {
        $paisId = DB::table('paises')->where('nombre', 'Colombia')->value('id');
        if (!$paisId) return;

        // "Indefinido" por cada departamento (útil para formularios)
        $departamentos = DB::table('departamentos')->where('pais_id', $paisId)->pluck('id', 'nombre');
        foreach ($departamentos as $nombreDepto => $deptId) {
            DB::table('ciudades')->updateOrInsert(
                ['nombre' => 'Indefinido', 'departamento_id' => $deptId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // CSV DIVIPOLA exportado (codigo_mpio, nombre, codigo_depto)
        $path = database_path('seeders/data/co_municipios.csv');
        if (!file_exists($path)) return;

        $rows = array_map('str_getcsv', file($path));
        $header = array_map('mb_strtolower', array_map('trim', array_shift($rows)));
        $cNombre = array_search('nombre', $header);
        $cDepto  = array_search('codigo_depto', $header);

        // Mapea cod_depto -> id departamento (si tu CSV tiene códigos)
        // Como usamos nombre en Departamentos CSV, puedes enriquecer co_departamentos.csv con 'codigo_depto'
        $mapDepto = DB::table('departamentos')
                      ->where('pais_id', $paisId)
                      ->pluck('id', 'nombre'); // o cambia a 'codigo' si le agregas columna

        $toUpsert = [];
        foreach ($rows as $r) {
            $nombre = trim($r[$cNombre] ?? '');
            $codigoDepto = trim($r[$cDepto] ?? '');

            if ($nombre === '' || mb_strtolower($nombre) === 'indefinido') continue;

            // Busca por nombre (o por código si añadiste columna codigo_depto en la tabla)
            // Aquí asumimos que co_municipios.csv trae también el nombre de departamento si prefieres.
            // Si usas código, agrega columna 'codigo' a 'departamentos' y mapea por ella.
            $departamentoId = null;
            // EJEMPLO: si en co_departamentos.csv agregas 'codigo_depto' puedes mapear por código
            // $departamentoId = $mapDeptoPorCodigo[$codigoDepto] ?? null;

            // Si no tienes código, puedes agregar columna 'departamento_nombre' en co_municipios.csv
            // y mapear: $departamentoId = $mapDepto[$deptoNombre] ?? null;

            if (!$departamentoId) continue;

            $toUpsert[] = [
                'nombre'          => $nombre,
                'departamento_id' => $departamentoId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            // Inserta por lotes para no llenar memoria
            if (count($toUpsert) >= 1000) {
                DB::table('ciudades')->upsert($toUpsert, ['departamento_id', 'nombre']);
                $toUpsert = [];
            }
        }

        if ($toUpsert) {
            DB::table('ciudades')->upsert($toUpsert, ['departamento_id', 'nombre']);
        }
    }
}
