<?php

namespace App\Http\Controllers\Api\WhatsAppHub;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WhatsAppHub\WordpressFormNotificationIngestRequest;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Services\WhatsAppHub\WordpressFormNotificationIngestService;
use Illuminate\Http\JsonResponse;
use Throwable;

class WordpressFormNotificationIngestController extends Controller
{
    /** @var WordpressFormNotificationIngestService */
    private $ingestService;

    public function __construct(WordpressFormNotificationIngestService $ingestService)
    {
        $this->ingestService = $ingestService;
    }

    public function store(WordpressFormNotificationIngestRequest $request): JsonResponse
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
            $result = $this->ingestService->ingestFromIntegration(
                $integration,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificación de formulario recibida correctamente.',
                'data' => $result,
            ], 202);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al recibir notificación de formulario.',
            ], 500);
        }
    }
}
