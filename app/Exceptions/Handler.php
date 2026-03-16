<?php

namespace App\Exceptions;

use App\Exceptions\IntegrationSecurity\BusinessServiceDeniedException;
use App\Exceptions\IntegrationSecurity\IntegrationInactiveException;
use App\Exceptions\IntegrationSecurity\IntegrationNotFoundException;
use App\Exceptions\IntegrationSecurity\InvalidSignatureException;
use App\Exceptions\IntegrationSecurity\NonceReplayException;
use App\Exceptions\IntegrationSecurity\RequestExpiredException;
use App\Exceptions\IntegrationSecurity\ScopeDeniedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ── Capa de seguridad de integraciones externas ───────────────────────
        // 401 → credencial inválida o firma incorrecta (no revelar distinción)
        $this->renderable(function (IntegrationNotFoundException $e, $request) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        });

        $this->renderable(function (InvalidSignatureException $e, $request) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        });

        $this->renderable(function (RequestExpiredException $e, $request) {
            return response()->json(['message' => 'Request timestamp expired.'], 401);
        });

        $this->renderable(function (NonceReplayException $e, $request) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        });

        // 403 → integración reconocida pero suspendida/revocada
        $this->renderable(function (IntegrationInactiveException $e, $request) {
            return response()->json(['message' => 'Integration is not active.'], 403);
        });

        // 403 → scope técnico ausente en la integración
        $this->renderable(function (ScopeDeniedException $e, $request) {
            return response()->json(['message' => 'Access denied: insufficient scope.'], 403);
        });

        // 403 → la empresa no tiene contratado el servicio requerido
        $this->renderable(function (BusinessServiceDeniedException $e, $request) {
            return response()->json(['message' => 'Access denied: required service not active.'], 403);
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof TokenMismatchException) {
            // Sesión expirada / CSRF inválido -> redirige al login con aviso
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión ha expirado. Por favor inicia sesión de nuevo.'
                ], 419);
            }

            // Limpia y redirige
            return redirect()->guest(route('login'))
                ->with('message', 'Tu sesión ha expirado. Inicia sesión nuevamente.');
        }

        return parent::render($request, $e);
    }
}
