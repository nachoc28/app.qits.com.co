<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InternalQitsEmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        if (! $ciudadId) {
            if ($this->command) {
                $this->command->warn('No hay ciudad base para crear la empresa interna QITS.');
            }
            return;
        }

        $attributes = [
            'nombre' => env('QITS_INTERNAL_NAME', 'QITS'),
            'direccion' => env('QITS_INTERNAL_ADDRESS', 'N/A'),
            'ciudad_id' => $ciudadId,
            'telefono' => env('QITS_INTERNAL_PHONE', null),
            'email' => env('QITS_INTERNAL_EMAIL', 'gerencia@qits.com.co'),
            'active' => true,
        ];

        if (Schema::hasColumn('empresas', 'is_internal')) {
            $attributes['is_internal'] = true;
        }

        Empresa::updateOrCreate(
            ['nit' => env('QITS_INTERNAL_NIT', 'QITS-INTERNAL')],
            $attributes
        );

        if ($this->command) {
            $this->command->info('Empresa interna QITS asegurada como registro normal en empresas (bootstrap opcional).');
        }
    }
}
