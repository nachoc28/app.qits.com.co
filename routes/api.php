<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UtmController;
use App\Http\Controllers\Api\LeadController;

Route::middleware('auth:sanctum')->group(function () {

    // UTM: solo escritura desde WP
    Route::post('/utm', [UtmController::class, 'store'])
        ->middleware('abilities:utm:write');

    // Leads: solo si habilitas esta integración
    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('abilities:leads:write');
});
