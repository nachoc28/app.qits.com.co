<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm ring-1 ring-emerald-100 sm:p-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-800">
                {{ ($result['persisted'] ?? false) ? 'Importación confirmada' : 'Resultado de la validación previa' }}
            </p>
            <p class="mt-1 text-xs text-emerald-700">
                Archivo: {{ $result['file_info']['filename'] ?? 'content-import.xlsx' }}
                · Empresa: {{ $result['file_info']['empresa_name'] ?? 'N/D' }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
            <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                <div class="font-semibold text-gray-900">{{ $result['total_rows'] ?? 0 }}</div>
                <div class="mt-1 text-gray-500">Total filas</div>
            </div>
            <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                <div class="font-semibold text-emerald-700">{{ $result['valid_rows'] ?? 0 }}</div>
                <div class="mt-1 text-gray-500">Filas válidas</div>
            </div>
            <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                <div class="font-semibold text-red-700">{{ count($result['errors'] ?? []) }}</div>
                <div class="mt-1 text-gray-500">Errores</div>
            </div>
            <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                <div class="font-semibold text-amber-700">{{ $result['duplicate_rows'] ?? 0 }}</div>
                <div class="mt-1 text-gray-500">Duplicados</div>
            </div>
        </div>
    </div>

    @if(($result['persisted'] ?? false) === true)
        <div class="mt-5 rounded-lg border border-green-200 bg-white p-4">
            <p class="text-sm font-medium text-green-800">Persistencia completada</p>
            <p class="mt-1 text-sm text-green-700">
                Se creó la importación `#{{ $result['import_id'] ?? '-' }}` y se registraron {{ $result['created'] ?? 0 }} artículos con sus 3 pasos iniciales.
            </p>
        </div>
    @endif

    @if(!empty($result['errors_preview']))
        <div class="mt-5 rounded-lg border border-red-200 bg-white p-4">
            <p class="text-sm font-medium text-red-800">Errores detectados</p>
            <ul class="mt-3 space-y-2 text-sm text-red-700">
                @foreach($result['errors_preview'] as $error)
                    <li class="rounded-md border border-red-100 bg-red-50 px-3 py-2 break-words">
                        <span class="font-semibold">Fila {{ $error['row'] ?? '-' }}</span>
                        <span class="text-red-500"> · {{ $error['field'] ?? 'general' }}</span>
                        <div class="mt-1 text-xs text-red-700">{{ $error['message'] ?? 'Error de validación.' }}</div>
                    </li>
                @endforeach
            </ul>

            @if(($result['errors_remaining'] ?? 0) > 0)
                <p class="mt-3 text-xs text-red-600">
                    Se omitieron {{ $result['errors_remaining'] }} errores adicionales para mantener la vista compacta.
                </p>
            @endif
        </div>
    @endif

    @if(empty($result['errors'] ?? []) && ($result['can_persist'] ?? false))
        <div class="mt-5 rounded-lg border border-green-200 bg-white p-4">
            <p class="text-sm font-medium text-green-800">Archivo listo para confirmar</p>
            <p class="mt-1 text-sm text-green-700">
                La validación previa no encontró errores ni duplicados. Puedes confirmar la importación definitiva.
            </p>
        </div>
    @endif
</div>
