<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Servicio;

class ServicioSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            [
                'slug'        => 'sitio-web',
                'nombre'      => 'Sitio Web',
                'descripcion' => 'Sitio web desarrollado o administrado por QITS. Incluye seguimiento SEO y tracker de UTM.',
                'activo'      => true,
            ],
            [
                'slug'        => 'formularios-whatsapp-api',
                'nombre'      => 'Gestión de envío de formularios vía WhatsApp API',
                'descripcion' => 'Integración que envía automáticamente los formularios del sitio web a través de la API de WhatsApp.',
                'activo'      => true,
            ],
            [
                'slug'        => 'whatsapp-qits-solution',
                'nombre'      => 'WhatsApp QITS Solution',
                'descripcion' => 'Solución QITS para gestionar y automatizar las interacciones por WhatsApp.',
                'activo'      => true,
            ],
        ];

        foreach ($servicios as $data) {
            Servicio::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
