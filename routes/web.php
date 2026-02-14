<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Livewire\Admin\Empresas\EmpresasManager;

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
