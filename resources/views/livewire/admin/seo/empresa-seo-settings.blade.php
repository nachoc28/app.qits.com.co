<div class="space-y-6">
    @if (session()->has('seo_settings_saved'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('seo_settings_saved') }}
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm ring-1 ring-blue-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-blue-900">Configuración SEO por empresa</h3>
                <p class="mt-1 text-sm text-blue-800">Aquí solo se definen URLs, propiedades e indicadores de origen para esta empresa. La autenticación y conectividad con Google se administran globalmente en el sistema.</p>
            </div>

            @if(auth()->check() && auth()->user()->isAdmin())
                <a href="{{ route('admin.system.google-status') }}"
                   class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                    Ver estado global Google
                </a>
            @endif
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Estado de configuración</h3>
                <p class="mt-1 text-sm text-gray-500">El dashboard SEO solo está disponible cuando el estado es <span class="font-medium">configured</span>.</p>
            </div>

            <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                @if($configurationStatus === 'configured') bg-green-100 text-green-700
                @elseif($configurationStatus === 'partially_configured') bg-yellow-100 text-yellow-700
                @else bg-red-100 text-red-700 @endif">
                {{ $configurationStatus }}
            </div>
        </div>

        @if(!empty($statusErrors))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3">
                <p class="text-sm font-medium text-red-800">Faltantes de configuración</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
                    @foreach($statusErrors as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($statusWarnings))
            <div class="mt-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3">
                <p class="text-sm font-medium text-yellow-800">Recomendaciones</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-yellow-700">
                    @foreach($statusWarnings as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <form wire:submit.prevent="saveSettings" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="min-w-0 xl:col-span-2">
                <label for="site_url" class="text-sm font-medium text-gray-700">Site URL <span class="text-red-600">*</span></label>
                <input
                    id="site_url"
                    type="url"
                    wire:model.defer="siteUrl"
                    placeholder="https://empresa.com"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <p class="mt-1 text-xs text-gray-500">URL principal del sitio web corporativo.</p>
                @error('siteUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="wordpress_site_url" class="text-sm font-medium text-gray-700">WordPress Site URL</label>
                <input
                    id="wordpress_site_url"
                    type="url"
                    wire:model.defer="wordpressSiteUrl"
                    placeholder="https://blog.empresa.com"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <p class="mt-1 text-xs text-gray-500">Recomendado cuando UTM Tracking está activo.</p>
                @error('wordpressSiteUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="search_console_property" class="text-sm font-medium text-gray-700">Search Console Property</label>
                <input
                    id="search_console_property"
                    type="text"
                    wire:model.defer="searchConsoleProperty"
                    placeholder="sc-domain:empresa.com"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <p class="mt-1 text-xs text-gray-500">Mapeo de la propiedad que usará esta empresa. Obligatorio si GSC Enabled está activo.</p>
                @error('searchConsoleProperty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="ga4_property_id" class="text-sm font-medium text-gray-700">GA4 Property ID</label>
                <input
                    id="ga4_property_id"
                    type="text"
                    wire:model.defer="ga4PropertyId"
                    placeholder="123456789"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <p class="mt-1 text-xs text-gray-500">Identificador GA4 asociado a esta empresa. Obligatorio si GA4 Enabled está activo.</p>
                @error('ga4PropertyId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                <input type="checkbox" wire:model.defer="utmTrackingEnabled" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-700">UTM Tracking Enabled</span>
                    <span class="block text-xs text-gray-500">Habilita el registro de conversiones UTM.</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                <input type="checkbox" wire:model.defer="gscEnabled" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-700">GSC Enabled</span>
                    <span class="block text-xs text-gray-500">Activa el uso de la propiedad Search Console configurada para esta empresa.</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                <input type="checkbox" wire:model.defer="ga4Enabled" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-700">GA4 Enabled</span>
                    <span class="block text-xs text-gray-500">Activa el uso del Property ID de GA4 configurado para esta empresa.</span>
                </span>
            </label>
        </div>

        <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-sm text-gray-700">Esta pantalla no almacena credenciales OAuth de Google por empresa. Solo guarda el mapeo funcional que usa la integración global del sistema.</p>
        </div>

        <div class="mt-6 flex items-center justify-end">
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Guardar configuración SEO
            </button>
        </div>
    </form>
</div>
