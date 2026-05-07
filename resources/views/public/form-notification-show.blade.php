<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de notificación</title>
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body class="bg-gray-100 antialiased">
    <div class="min-h-screen py-8">
        <div class="mx-auto w-full max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h1 class="text-lg font-semibold text-gray-900">Detalle de notificación</h1>
                    <p class="mt-1 text-sm text-gray-600">Vista pública de solo lectura.</p>
                </div>

                <div class="px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Nombre</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['nombre'] !== '' ? $safeData['nombre'] : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Servicio</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['servicio'] !== '' ? $safeData['servicio'] : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Telefono</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['telefono'] !== '' ? $safeData['telefono'] : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Email</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['email'] !== '' ? $safeData['email'] : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Formulario</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['form_name'] !== '' ? $safeData['form_name'] : 'N/A' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Page URL</p>
                            @if ($safeData['page_url'] !== '')
                                <a href="{{ $safeData['page_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block break-all text-sm text-blue-700 hover:underline">{{ $safeData['page_url'] }}</a>
                            @else
                                <p class="mt-1 text-sm text-gray-900">N/A</p>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha de envio</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $safeData['submitted_at'] !== '' ? $safeData['submitted_at'] : 'N/A' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Mensaje / comentario</p>
                            <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-900">{{ $safeData['mensaje'] !== '' ? $safeData['mensaje'] : 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
