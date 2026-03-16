<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Empresa;
use App\Services\Seo\SeoPropertyConfigurationService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::view('/admin/empresas', 'admin.empresas.index')->name('admin.empresas');

    Route::get('/admin/empresas/{empresa}/seo', function (Empresa $empresa) {
        /** @var SeoPropertyConfigurationService $configurationService */
        $configurationService = app(SeoPropertyConfigurationService::class);
        $state = $configurationService->state($empresa);

        if ($state->isNotConfigured()) {
            return redirect()->route('admin.seo.empresa-settings', $empresa);
        }

        return redirect()->route('admin.seo.empresa-dashboard', $empresa);
    })->name('admin.empresas.seo-entry');

    Route::get('/admin/seo', function () {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $empresaId = null;

        if ($user->isAdmin()) {
            $empresaId = Empresa::query()->orderBy('nombre')->value('id');
        } else {
            $empresaId = $user->empresa_id;
        }

        abort_if(! $empresaId, 404, 'No hay empresa disponible para dashboard SEO.');

        /** @var Empresa|null $empresa */
        $empresa = Empresa::query()->find($empresaId);
        abort_if(! $empresa, 404, 'No hay empresa disponible para configuración SEO.');

        return redirect()->route('admin.seo.empresa-dashboard', $empresa);
    })->name('admin.seo');

    Route::get('/admin/system/google', function () {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        abort_if(! $user || ! $user->isAdmin(), 403);

        return view('admin.system.google-status');
    })->name('admin.system.google-status');

    Route::get('/admin/seo/{empresa}/settings', function (Empresa $empresa) {
        return view('admin.seo.empresa-settings', compact('empresa'));
    })->name('admin.seo.empresa-settings');

    Route::get('/admin/seo/{empresa}', function (Empresa $empresa) {
        return view('admin.seo.empresa-dashboard', compact('empresa'));
    })->name('admin.seo.empresa-dashboard');
});

if (app()->environment('local')) {
    Route::get('/dev/reseed-api-token', function () {
        $email = env('API_EMAIL', 'api@example.com');
        /** @var \App\Models\User $u */
        $u = User::where('email', $email)->firstOrFail();

        // opcional: revocar tokens previos
        $u->tokens()->delete();

        // abilities que quieras dar al token
        $abilities = ['utm:write', 'leads:write']; // o solo 'utm:write'
        $token = $u->createToken(env('API_TOKEN_NAME', 'qits-api'), $abilities)->plainTextToken;

        // devuelvo el token en texto plano para copiar
        return response($token, 200, ['Content-Type' => 'text/plain']);
    });
}
