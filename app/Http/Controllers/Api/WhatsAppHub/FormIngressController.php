<?php

namespace App\Http\Controllers\Api\WhatsAppHub;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatsAppHub\FormIngressRequest;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Services\WhatsAppHub\FormIngressService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Recibe formularios web desde sitios externos vía POST público.
 *
 * Flujo:
 *  1. El middleware `integration.auth` autentica y autoriza la solicitud.
 *  2. FormIngressRequest valida campos de negocio.
 *  3. El controlador delega al FormIngressService.
 *  4. Retorna JSON normalizado.
 */
class FormIngressController extends Controller
{
    private FormIngressService    $ingressService;

    public function __construct(FormIngressService $ingressService)
    {
        $this->ingressService  = $ingressService;
    }

    public function receive(FormIngressRequest $request, string $siteKey): JsonResponse
    {
        /** @var Empresa|null $empresa */
        $empresa = $request->attributes->get('empresa');

        /** @var EmpresaIntegration|null $integration */
        $integration = $request->attributes->get('integration');

        if (! $empresa instanceof Empresa || ! $integration instanceof EmpresaIntegration) {
            return response()->json([
                'success' => false,
                'message' => 'Security context is missing in request.',
            ], 500);
        }

        try {
            $result = $this->ingressService->processForAuthorizedEmpresa(
                $empresa,
                $siteKey,
                $request->validated(),
                [
                    'integration_id' => $integration->id,
                    'public_key' => $integration->public_key,
                    'origin'  => $request->header('Origin'),
                    'referer' => $request->header('Referer'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Formulario recibido correctamente.',
                'data'    => $result->toArray(),
            ], 201);

        } catch (\App\Exceptions\WhatsAppHub\InvalidSiteKeyException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La clave del formulario no existe o está inactiva para esta empresa.',
            ], 403);

        } catch (\App\Exceptions\WhatsAppHub\DomainNotAllowedException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dominio de origen no autorizado para esta regla de formulario.',
            ], 403);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error interno. Intente más tarde.',
            ], 500);
        }
    }
}
