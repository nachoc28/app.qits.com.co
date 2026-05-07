<?php

namespace App\Http\Controllers;

use App\Models\FormNotificationPublicLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicFormNotificationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $token = trim($token);
        if ($token === '') {
            abort(404);
        }

        $tokenHash = hash('sha256', $token);

        $link = FormNotificationPublicLink::query()
            ->with('whatsappFormNotification')
            ->where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $link instanceof FormNotificationPublicLink || ! $link->whatsappFormNotification) {
            abort(404);
        }

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

        $notification = $link->whatsappFormNotification;
        $normalized = is_array($notification->normalized_payload_json)
            ? $notification->normalized_payload_json
            : [];

        $safeData = [
            'nombre' => (string) ($normalized['nombre'] ?? ''),
            'servicio' => (string) ($normalized['servicio'] ?? ''),
            'telefono' => (string) ($normalized['telefono'] ?? ''),
            'email' => (string) ($normalized['email'] ?? ''),
            'form_name' => (string) ($normalized['form_name'] ?? ''),
            'page_url' => (string) ($normalized['page_url'] ?? ''),
            'submitted_at' => (string) ($normalized['submitted_at'] ?? ''),
            'mensaje' => (string) ($normalized['mensaje'] ?? ($normalized['comentario'] ?? '')),
        ];

        return response()->view('public.form-notification-show', [
            'safeData' => $safeData,
        ]);
    }
}
