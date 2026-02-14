<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utm;
use Illuminate\Http\Request;

class UtmController extends Controller
{
    public function store(Request $r)
    {
        // payload mínimo esperado desde WP (ajústalo a tu plugin)
        $data = $r->only([
            'proyecto_empresa_id',
            'fecha',
            'event_type',
            'visitor_guid',
            'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
            'ip_address','landing_url','extra_data',
        ]);

        // crea sin validaciones complejas por ahora
        $utm = Utm::create($data);

        return response()->json(['ok' => true, 'id' => $utm->id]);
    }
}
