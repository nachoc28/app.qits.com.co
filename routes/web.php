<?php

use App\Http\Controllers\Integrations\GoogleOAuthController;
use App\Http\Controllers\Admin\ContentManagement\ContentArticleFileDownloadController;
use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowStrategicOutput;
use App\Models\AiFlowVersion;
use App\Http\Controllers\PublicFormNotificationController;
use App\Models\ContentArticle;
use App\Models\Empresa;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\Seo\SeoPropertyConfigurationService;
use Illuminate\Support\Facades\Route;

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

Route::get('/s/{token}', [PublicFormNotificationController::class, 'show'])
    ->name('public.form-notification.show')
    ->where('token', '[A-Za-z0-9\-_=]+');

Route::get('/s/{token}/pdf', [PublicFormNotificationController::class, 'downloadPdf'])
    ->name('public.form-notification.pdf')
    ->where('token', '[A-Za-z0-9\-_=]+');


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('admin.empresas');
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

    Route::view('/admin/content-management', 'admin.content-management.index')
        ->name('admin.content-management.index');

    Route::view('/admin/content-management/imports', 'admin.content-management.imports')
        ->name('admin.content-management.imports');

    Route::get('/admin/content-management/articles/{article}', function (ContentArticle $article, ContentAccessService $accessService) {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $article->loadMissing('contentImport.empresa');

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return view('admin.content-management.show', compact('article'));
    })->name('admin.content-management.articles.show');

    Route::get('/admin/content-management/articles/{article}/files/{file}/download', ContentArticleFileDownloadController::class)
        ->name('admin.content-management.articles.files.download');

    Route::get('/admin/ai-flow-executions', function (\App\Services\AiFlows\AiFlowAccessService $accessService) {
        abort_unless($accessService->canExecuteFlows(auth()->user()), 403);

        return view('admin.ai-flows.executions.index');
    })->name('admin.ai-flow-executions.index');

    Route::get('/admin/ai-flow-executions/create', function (\App\Services\AiFlows\AiFlowAccessService $accessService) {
        abort_unless($accessService->canExecuteFlows(auth()->user()), 403);

        return view('admin.ai-flows.executions.create');
    })->name('admin.ai-flow-executions.create');

    Route::get('/admin/ai-flow-executions/{execution}', function (AiFlowExecution $execution, \App\Services\AiFlows\AiFlowAccessService $accessService) {
        abort_unless($accessService->canAccessExecution(auth()->user(), $execution), 403);

        return view('admin.ai-flows.executions.show', compact('execution'));
    })->name('admin.ai-flow-executions.show');

    Route::get('/admin/ai-flow-strategic-outputs', function (\App\Services\AiFlows\AiFlowAccessService $accessService) {
        abort_unless($accessService->canViewStrategicOutputs(auth()->user()), 403);

        return view('admin.ai-flows.strategic-outputs.index');
    })->name('admin.ai-flow-strategic-outputs.index');

    Route::get('/admin/ai-flow-strategic-outputs/{output}', function (AiFlowStrategicOutput $output, \App\Services\AiFlows\AiFlowAccessService $accessService) {
        abort_unless($accessService->canViewStrategicOutputs(auth()->user()), 403);

        $output->loadMissing([
            'empresa',
            'execution.flow',
            'executionStep.step',
            'markedBy',
        ]);

        return view('admin.ai-flows.strategic-outputs.show', [
            'output' => $output,
            'typeLabel' => \App\Support\AiFlowLabels::strategicOutputType($output->type),
        ]);
    })->name('admin.ai-flow-strategic-outputs.show');

    Route::get('/admin/ai-flows', function () {
        abort_unless(auth()->user() && auth()->user()->isAdmin(), 403);

        return view('admin.ai-flows.index');
    })->name('admin.ai-flows.index');

    Route::get('/admin/ai-flows/create', function () {
        abort_unless(auth()->user() && auth()->user()->isAdmin(), 403);

        return view('admin.ai-flows.create');
    })->name('admin.ai-flows.create');

    Route::get('/admin/ai-flows/{flow}/edit', function (AiFlow $flow) {
        abort_unless(auth()->user() && auth()->user()->isAdmin(), 403);

        return view('admin.ai-flows.edit', compact('flow'));
    })->name('admin.ai-flows.edit');

    Route::get('/admin/ai-flows/{flow}/versions', function (AiFlow $flow) {
        abort_unless(auth()->user() && auth()->user()->isAdmin(), 403);

        return view('admin.ai-flows.versions.index', compact('flow'));
    })->name('admin.ai-flows.versions.index');

    Route::get('/admin/ai-flows/{flow}/versions/{version}', function (AiFlow $flow, AiFlowVersion $version) {
        abort_unless(auth()->user() && auth()->user()->isAdmin(), 403);
        abort_unless((int) $version->ai_flow_id === (int) $flow->id, 404);

        return view('admin.ai-flows.versions.show', compact('flow', 'version'));
    })->name('admin.ai-flows.versions.show');

    Route::get('/google/connect', [GoogleOAuthController::class, 'connect']);
    Route::get('/google/callback', [GoogleOAuthController::class, 'callback']);
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
