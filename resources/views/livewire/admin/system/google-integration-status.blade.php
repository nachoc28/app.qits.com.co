<div class="space-y-6">
    @if (session()->has('google_health_status_refreshed'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('google_health_status_refreshed') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Estado global de Google</h3>
                <p class="mt-1 text-sm text-gray-500">Diagnóstico del OAuth central del sistema. Esta pantalla no permite editar secretos ni muestra valores sensibles.</p>
            </div>

            <div class="flex flex-col items-start gap-2 sm:items-end">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                    @if($healthState === 'connected') bg-green-100 text-green-700
                    @elseif($healthState === 'partially_configured') bg-yellow-100 text-yellow-700
                    @elseif($healthState === 'failed') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-700 @endif">
                    {{ $healthState }}
                </span>

                <button
                    type="button"
                    wire:click="refreshStatus"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Actualizar diagnóstico
                </button>
            </div>
        </div>

        @if($lastCheckedAt)
            <p class="mt-4 text-xs text-gray-500">Última verificación actual: {{ $lastCheckedAt }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
            <p class="text-sm font-medium text-gray-600">Client ID configurado</p>
            <p class="mt-3 text-2xl font-semibold {{ $clientIdConfigured ? 'text-green-600' : 'text-red-600' }}">
                {{ $clientIdConfigured ? 'Sí' : 'No' }}
            </p>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
            <p class="text-sm font-medium text-gray-600">Redirect URI configurado</p>
            <p class="mt-3 text-2xl font-semibold {{ $redirectUriConfigured ? 'text-green-600' : 'text-red-600' }}">
                {{ $redirectUriConfigured ? 'Sí' : 'No' }}
            </p>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
            <p class="text-sm font-medium text-gray-600">Refresh token disponible</p>
            <p class="mt-3 text-2xl font-semibold {{ $refreshTokenConfigured ? 'text-green-600' : 'text-red-600' }}">
                {{ $refreshTokenConfigured ? 'Sí' : 'No' }}
            </p>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5">
            <p class="text-sm font-medium text-gray-600">Prueba de conexión</p>
            <p class="mt-3 text-2xl font-semibold {{ $connectionTestPassed ? 'text-green-600' : 'text-red-600' }}">
                {{ $connectionTestPassed ? 'OK' : 'Falló' }}
            </p>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <h3 class="text-base font-semibold text-gray-900">Último estado conocido</h3>

        @if($hasLastKnownStatus)
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                    @if($lastKnownHealthState === 'connected') bg-green-100 text-green-700
                    @elseif($lastKnownHealthState === 'partially_configured') bg-yellow-100 text-yellow-700
                    @elseif($lastKnownHealthState === 'failed') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-700 @endif">
                    {{ $lastKnownHealthState }}
                </span>

                <p class="text-sm text-gray-500">
                    @if($lastKnownCheckedAt)
                        Registrado en {{ $lastKnownCheckedAt }}
                    @else
                        Sin marca de tiempo disponible.
                    @endif
                </p>
            </div>
        @else
            <p class="mt-4 text-sm text-gray-500">Todavía no hay un diagnóstico previo almacenado.</p>
        @endif
    </div>
</div>
