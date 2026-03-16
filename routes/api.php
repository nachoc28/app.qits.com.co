<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UtmController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\WhatsAppHub\FormIngressController;
use App\Http\Controllers\Api\Seo\UtmConversionIngestController;

/*
|-|------------------------------------------------------------------------
| WhatsApp Hub — Módulo 1: ingreso de formularios web
|-|------------------------------------------------------------------------
| Ruta protegida por la capa genérica de seguridad de integraciones.
| Requiere scope técnico: module1.form_ingress
| Requiere servicio de negocio activo: módulo 1
*/
Route::post(
    '/form-ingress/{site_key}',
    [FormIngressController::class, 'receive']
)->middleware('integration.auth:module1.form_ingress')
 ->where('site_key', '[A-Za-z0-9_\-]+');

/*
|-|------------------------------------------------------------------------
| SEO Module — UTM conversions ingest
|-|------------------------------------------------------------------------
| Endpoint para que sitios WordPress envíen conversiones UTM firmadas.
| Requiere scope técnico: seo.utm_conversions_ingest
| Requiere servicio de negocio activo: sitio-web
*/
Route::post(
    '/seo/utm-conversions',
    [UtmConversionIngestController::class, 'store']
)->middleware('integration.auth:seo.utm_conversions_ingest');

/*
|--------------------------------------------------------------------------
| Rutas protegidas por Sanctum
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // UTM: solo escritura desde WP
    Route::post('/utm', [UtmController::class, 'store'])
        ->middleware('abilities:utm:write');

    // Leads: solo si habilitas esta integración
    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('abilities:leads:write');
});
