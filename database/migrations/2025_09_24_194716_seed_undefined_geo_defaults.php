<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // País "Indefinido"
        $paisId = DB::table('paises')->where('nombre','Indefinido')->value('id');
        if (!$paisId) {
            $paisId = DB::table('paises')->insertGetId([
                'nombre' => 'Indefinido',
                'iso2' => null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // Departamento "Indefinido" para cada país
        $paises = DB::table('paises')->pluck('id');
        foreach ($paises as $pid) {
            DB::table('departamentos')->updateOrInsert(
                ['pais_id'=>$pid,'nombre'=>'Indefinido'],
                ['codigo_divipola'=>null,'created_at'=>now(),'updated_at'=>now()]
            );
        }

        // Ciudad "Indefinido" para cada departamento
        $deptos = DB::table('departamentos')->pluck('id');
        foreach ($deptos as $did) {
            DB::table('ciudades')->updateOrInsert(
                ['departamento_id'=>$did,'nombre'=>'Indefinido'],
                ['codigo_divipola'=>null,'created_at'=>now(),'updated_at'=>now()]
            );
        }
    }

    public function down(): void {
        // No borramos nada para no romper FKs.
    }
};
