<?php

namespace App\Http\Controllers;

use App\Models\FormNotificationPublicLink;
use App\Models\WhatsappFormNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicFormNotificationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $resolved = $this->resolvePublicNotification($token);
        $this->registerPublicAccess($resolved['link']);
        $canDownloadPdf = app()->bound('dompdf.wrapper');

        return response()->view('public.form-notification-show', [
            'mainData' => $resolved['mainData'],
            'additionalFields' => $resolved['additionalFields'],
            'token' => trim($token),
            'canDownloadPdf' => $canDownloadPdf,
        ]);
    }

    public function downloadPdf(Request $request, string $token): Response
    {
        $resolved = $this->resolvePublicNotification($token);
        $this->registerPublicAccess($resolved['link']);

        if (! app()->bound('dompdf.wrapper')) {
            abort(503, 'La libreria PDF no esta instalada (barryvdh/laravel-dompdf).');
        }

        $wrapper = app('dompdf.wrapper');
        $pdfBinary = $wrapper
            ->loadView('pdf.public.form-notification', [
                'mainData' => $resolved['mainData'],
                'additionalFields' => $resolved['additionalFields'],
                'notificationId' => $resolved['notification']->id,
            ])
            ->setPaper('a4')
            ->output();

        $fileName = 'solicitud-formulario-' . $resolved['notification']->id . '.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @return array{link:FormNotificationPublicLink,notification:WhatsappFormNotification,mainData:array<string,mixed>,additionalFields:array<string,string>}
     */
    private function resolvePublicNotification(string $token): array
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            abort(404);
        }

        $tokenHash = hash('sha256', $cleanToken);

        $link = FormNotificationPublicLink::query()
            ->with('whatsappFormNotification')
            ->where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $link instanceof FormNotificationPublicLink || ! $link->whatsappFormNotification instanceof WhatsappFormNotification) {
            abort(404);
        }

        $mappedData = $this->mapNotificationData($link->whatsappFormNotification);

        return [
            'link' => $link,
            'notification' => $link->whatsappFormNotification,
            'mainData' => $mappedData['mainData'],
            'additionalFields' => $mappedData['additionalFields'],
        ];
    }

    private function registerPublicAccess(FormNotificationPublicLink $link): void
    {
        $now = now();
        $updates = [
            'last_accessed_at' => $now,
            'access_count' => DB::raw('access_count + 1'),
        ];

        if ($link->first_accessed_at === null) {
            $updates['first_accessed_at'] = $now;
        }

        FormNotificationPublicLink::query()
            ->whereKey($link->id)
            ->update($updates);
    }

    /**
     * @return array{mainData:array<string,mixed>,additionalFields:array<string,string>}
     */
    private function mapNotificationData(WhatsappFormNotification $notification): array
    {
        $normalized = is_array($notification->normalized_payload_json)
            ? $notification->normalized_payload_json
            : [];

        $mainData = [
            'nombre' => (string) ($normalized['nombre'] ?? ''),
            'apellido' => (string) ($normalized['apellido'] ?? ''),
            'nombre_completo' => (string) ($normalized['nombre_completo'] ?? ''),
            'servicio' => (string) ($normalized['servicio'] ?? ''),
            'telefono' => (string) ($normalized['telefono'] ?? ''),
            'email' => (string) ($normalized['email'] ?? ''),
            'form_name' => (string) ($normalized['form_name'] ?? ''),
            'page_url' => (string) ($normalized['page_url'] ?? ''),
            'submitted_at' => (string) ($normalized['submitted_at'] ?? ''),
            'mensaje' => (string) ($normalized['mensaje'] ?? ''),
            'consentimiento' => array_key_exists('consentimiento', $normalized)
                ? $normalized['consentimiento']
                : null,
        ];

        $additionalRaw = is_array($normalized['campos_adicionales'] ?? null)
            ? $normalized['campos_adicionales']
            : [];

        $additionalFields = [];
        foreach ($additionalRaw as $label => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $safeLabel = trim((string) $label);
            if ($safeLabel === '' || $this->isSensitiveLabel($safeLabel)) {
                continue;
            }

            $additionalFields[$safeLabel] = trim((string) ($value ?? ''));
        }

        return [
            'mainData' => $mainData,
            'additionalFields' => $additionalFields,
        ];
    }

    private function isSensitiveLabel(string $label): bool
    {
        $normalized = strtolower(trim($label));
        if ($normalized === '') {
            return false;
        }

        $keywords = [
            'token',
            'hash',
            'secret',
            'password',
            'clave',
            'authorization',
            'bearer',
            'api_key',
            'apikey',
            'firma',
            'signature',
        ];

        foreach ($keywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
