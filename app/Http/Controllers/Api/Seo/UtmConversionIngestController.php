<?php

namespace App\Http\Controllers\Api\Seo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Seo\UtmConversionIngestRequest;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Services\Seo\UtmConversionIngestService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Endpoint de ingesta UTM para integraciones externas (WordPress, etc.).
 *
 * Flujo:
 *  1. Middleware integration.auth autentica + autoriza + adjunta contexto.
 *  2. FormRequest valida payload de negocio.
 *  3. Controlador delega al servicio y retorna JSON normalizado.
 */
class UtmConversionIngestController extends Controller
{
    /** @var UtmConversionIngestService */
    private $ingestService;

    public function __construct(UtmConversionIngestService $ingestService)
    {
        $this->ingestService = $ingestService;
    }

    public function store(UtmConversionIngestRequest $request): JsonResponse
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
            $conversion = $this->ingestService->ingestFromIntegration(
                $integration,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Conversión UTM registrada correctamente.',
                'data'    => [
                    'id' => $conversion->id,
                    'empresa_id' => $conversion->empresa_id,
                    'conversion_datetime' => optional($conversion->conversion_datetime)->toDateTimeString(),
                ],
            ], 201);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al registrar conversión UTM.',
            ], 500);
        }
    }
}
