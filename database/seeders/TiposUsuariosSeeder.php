<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoUsuario;

class TiposUsuariosSeeder extends Seeder
{
    public function run(): void
    {
        // evita duplicados si lo corres varias veces
        $tipos = ['Administrador', 'Cliente', 'API User'];

        foreach ($tipos as $nombre) {
            TipoUsuario::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
