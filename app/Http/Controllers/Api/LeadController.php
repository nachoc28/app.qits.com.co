<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $r)
    {
        $data = $r->only([
            'nombre','email','telefono','tipo_proyecto_id','mensaje','origen'
        ]);
        // status por defecto
        $data['status'] = 'nuevo';

        $lead = Lead::create($data);

        return response()->json(['ok' => true, 'id' => $lead->id]);
    }
}
