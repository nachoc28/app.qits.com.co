<div class="space-y-6">
    @if (session()->has('seo_settings_saved'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('seo_settings_saved') }}
        </div>
    @endif

    @if (session()->has('utm_csv_import_saved'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('utm_csv_import_saved') }}
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm ring-1 ring-blue-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-blue-900">
                    {{ $isEditingConfiguration ? 'Editar configuración SEO' : 'Configurar SEO por primera vez' }}
                </h3>
                <p class="mt-1 text-sm text-blue-800">
                    {{ $isEditingConfiguration
                        ? 'Estás editando una configuración SEO existente para esta empresa.'
                        : 'Estás realizando la configuración inicial SEO para esta empresa.' }}
                    Aquí solo se definen URLs, propiedades e indicadores de origen. La autenticación y conectividad con Google se administran globalmente en el sistema.
                </p>
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
                <button type="button" wire:click="testGscConnection" wire:loading.attr="disabled" class="mt-2 inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:opacity-50">
                    @if($testGscLoading)
                        <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-r-gray-700 mr-2"></span>
                    @endif
                    Test Search Console API
                </button>
                <button type="button" wire:click="runGscSyncNow" wire:loading.attr="disabled" class="mt-2 ml-2 inline-flex items-center rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                    @if($syncGscLoading)
                        <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-indigo-300 border-r-indigo-700 mr-2"></span>
                    @endif
                    Run GSC Sync Now
                </button>
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
                <button type="button" wire:click="testGa4Connection" wire:loading.attr="disabled" class="mt-2 inline-flex items-center rounded-lg bg-gray-100 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:opacity-50">
                    @if($testGa4Loading)
                        <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-r-gray-700 mr-2"></span>
                    @endif
                    Test GA4 API
                </button>
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

        @if($testGscError)
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Error en Test GSC</p>
                <p class="mt-1 text-sm text-red-700">{{ $testGscError }}</p>
            </div>
        @endif

        @if($testGscResult && !$testGscError)
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4">
                <p class="text-sm font-medium text-green-800">Test GSC Exitoso</p>
                <div class="mt-2 text-xs text-green-700 space-y-1">
                    <p><strong>Propiedad:</strong> {{ $testGscResult['property'] }}</p>
                    <p><strong>Rango:</strong> {{ $testGscResult['dateRange'] }}</p>
                    <p><strong>Filas retornadas:</strong> {{ $testGscResult['rowCount'] }}</p>
                    @if($testGscResult['rowCount'] > 0)
                        <p><strong>Primeras filas:</strong></p>
                        <ul class="ml-4 list-disc">
                            @foreach($testGscResult['firstRows'] as $row)
                                <li>{{ $row['date'] }} - clics: {{ $row['clicks'] }}, impresiones: {{ $row['impressions'] }}, ctr: {{ number_format($row['ctr'], 4) }}, posición: {{ number_format($row['avg_position'], 2) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        @if($testGa4Error)
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Error en Test GA4</p>
                <p class="mt-1 text-sm text-red-700">{{ $testGa4Error }}</p>
            </div>
        @endif

        @if($testGa4Result && !$testGa4Error)
            <div class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4">
                <p class="text-sm font-medium text-green-800">Test GA4 Exitoso</p>
                <div class="mt-2 text-xs text-green-700 space-y-1">
                    <p><strong>GA4 Property ID:</strong> {{ $testGa4Result['ga4PropertyId'] }}</p>
                    <p><strong>Rango:</strong> {{ $testGa4Result['dateRange'] }}</p>
                    <p><strong>Filas retornadas:</strong> {{ $testGa4Result['rowCount'] }}</p>
                    @if($testGa4Result['rowCount'] > 0)
                        <p><strong>Primeras filas:</strong></p>
                        <ul class="ml-4 list-disc">
                            @foreach($testGa4Result['firstRows'] as $row)
                                <li>{{ $row['date'] }} - usuarios: {{ $row['users'] }}, sesiones: {{ $row['sessions'] }}, sesiones comprometidas: {{ $row['engaged_sessions'] ?? 'N/A' }}, conversiones: {{ $row['conversions'] ?? 'N/A' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        @if($syncGscError)
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Error en GSC Sync Now</p>
                <p class="mt-1 text-sm text-red-700">{{ $syncGscError }}</p>
            </div>
        @endif

        @if($syncGscResult)
            <div class="mt-6 rounded-lg border border-{{ $syncGscResult['synced'] ? 'indigo' : 'red' }}-200 bg-{{ $syncGscResult['synced'] ? 'indigo' : 'red' }}-50 p-4">
                <p class="text-sm font-medium text-{{ $syncGscResult['synced'] ? 'indigo' : 'red' }}-800">
                    GSC Sync Now — {{ $syncGscResult['synced'] ? 'Completado' : 'Fallido' }}
                </p>
                <div class="mt-2 text-xs text-{{ $syncGscResult['synced'] ? 'indigo' : 'red' }}-700 space-y-1">
                    <p><strong>Empresa ID:</strong> {{ $syncGscResult['empresa_id'] }}</p>
                    <p><strong>Propiedad:</strong> {{ $syncGscResult['property'] }}</p>
                    <p><strong>Rango sincronizado:</strong> {{ $syncGscResult['dateRange'] }}</p>
                    <p><strong>Filas API — diarias:</strong> {{ $syncGscResult['daily_rows'] }}</p>
                    <p><strong>Filas API — queries:</strong> {{ $syncGscResult['query_rows'] }}</p>
                    <p><strong>Filas API — páginas:</strong> {{ $syncGscResult['page_rows'] }}</p>
                </div>
                <div class="mt-3 border-t border-{{ $syncGscResult['synced'] ? 'indigo' : 'red' }}-200 pt-3 text-xs space-y-1 text-gray-700">
                    <p class="font-semibold text-gray-800">Verificación en tablas locales (empresa_id {{ $syncGscResult['empresa_id'] }}):</p>
                    <p><strong>seo_gsc_daily_metrics:</strong> {{ $syncGscResult['stored_daily'] }} fila(s) total</p>
                    <p><strong>seo_gsc_queries:</strong> {{ $syncGscResult['stored_queries'] }} fila(s) total</p>
                    <p><strong>seo_gsc_pages:</strong> {{ $syncGscResult['stored_pages'] }} fila(s) total</p>
                    <p><strong>Última fecha en daily_metrics:</strong>
                        {{ $syncGscResult['last_stored_date'] ?? 'sin datos' }}
                    </p>
                </div>
            </div>
        @endif

        <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p class="text-sm text-gray-700">Esta pantalla no almacena credenciales OAuth de Google por empresa. Solo guarda el mapeo funcional que usa la integración global del sistema.</p>
        </div>

        <div class="mt-6 flex items-center justify-end">
            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ $isEditingConfiguration ? 'Actualizar configuración SEO' : 'Guardar configuración SEO' }}
            </button>
        </div>
    </form>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Importación histórica UTM</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Carga un CSV exportado desde WordPress para importar conversiones históricas. El archivo se guarda temporalmente, se procesa desde el servicio SEO y luego se elimina.
                </p>
            </div>
        </div>

        <form wire:submit.prevent="importUtmCsv" class="mt-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="min-w-0 xl:col-span-2">
                    <label for="utm_csv_file" class="text-sm font-medium text-gray-700">Archivo CSV UTM</label>
                    <input
                        id="utm_csv_file"
                        type="file"
                        wire:model="utmCsvFile"
                        accept=".csv,text/csv"
                        class="mt-1 block w-full text-sm text-gray-700"
                    >
                    <p class="mt-1 text-xs text-gray-500">Solo CSV. Máximo 10 MB. Se valida dominio, duplicados e idempotencia antes de persistir.</p>
                    @error('utmCsvFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="utmCsvFile,importUtmCsv"
                        class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="importUtmCsv">Importar CSV UTM</span>
                        <span wire:loading wire:target="importUtmCsv" class="inline-flex items-center">
                            <span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-200 border-r-white"></span>
                            Procesando...
                        </span>
                    </button>
                </div>
            </div>

            <div wire:loading wire:target="utmCsvFile" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Subiendo archivo temporal para validación.
            </div>

            <div wire:loading wire:target="importUtmCsv" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Procesando importación histórica. Esta operación puede tardar varios segundos según el tamaño del CSV.
            </div>
        </form>

        @if($utmCsvImportError)
            <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Error en importación CSV</p>
                <p class="mt-1 text-sm text-red-700">{{ $utmCsvImportError }}</p>
            </div>
        @endif

        @if($utmCsvImportResult)
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-emerald-800">Resultado de la importación</p>
                        <p class="mt-1 text-xs text-emerald-700">
                            Archivo: {{ $utmCsvImportResult['file_info']['filename'] ?? 'CSV' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                        <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                            <div class="font-semibold text-gray-900">{{ $utmCsvImportResult['total_rows'] ?? 0 }}</div>
                            <div class="mt-1 text-gray-500">Total</div>
                        </div>
                        <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                            <div class="font-semibold text-emerald-700">{{ $utmCsvImportResult['created'] ?? 0 }}</div>
                            <div class="mt-1 text-gray-500">Creadas</div>
                        </div>
                        <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                            <div class="font-semibold text-amber-700">{{ $utmCsvImportResult['skipped_duplicate'] ?? 0 }}</div>
                            <div class="mt-1 text-gray-500">Duplicadas</div>
                        </div>
                        <div class="rounded-lg bg-white px-3 py-2 text-center shadow-sm ring-1 ring-emerald-100">
                            <div class="font-semibold text-red-700">{{ $utmCsvImportResult['failed'] ?? 0 }}</div>
                            <div class="mt-1 text-gray-500">Fallidas</div>
                        </div>
                    </div>
                </div>

                @if(!empty($utmCsvImportResult['errors_preview']))
                    <div class="mt-5 rounded-lg border border-red-200 bg-white p-4">
                        <p class="text-sm font-medium text-red-800">Errores detectados</p>
                        <ul class="mt-3 space-y-2 text-sm text-red-700">
                            @foreach($utmCsvImportResult['errors_preview'] as $error)
                                <li class="rounded-md border border-red-100 bg-red-50 px-3 py-2 break-words">
                                    <span class="font-semibold">Fila {{ $error['row'] ?? '-' }}</span>
                                    @if(!empty($error['csv_id']))
                                        <span class="text-red-500"> · ID {{ $error['csv_id'] }}</span>
                                    @endif
                                    <div class="mt-1 text-xs text-red-700">{{ $error['message'] ?? 'Error de importación.' }}</div>
                                </li>
                            @endforeach
                        </ul>

                        @if(($utmCsvImportResult['errors_remaining'] ?? 0) > 0)
                            <p class="mt-3 text-xs text-red-600">
                                Se omitieron {{ $utmCsvImportResult['errors_remaining'] }} errores adicionales para mantener la vista compacta.
                            </p>
                        @endif
                    </div>
                @endif

                @if(!empty($utmCsvImportResult['warnings_preview']))
                    <div class="mt-5 rounded-lg border border-yellow-200 bg-white p-4">
                        <p class="text-sm font-medium text-yellow-800">Advertencias</p>
                        <ul class="mt-3 space-y-2 text-sm text-yellow-700">
                            @foreach($utmCsvImportResult['warnings_preview'] as $warning)
                                <li class="rounded-md border border-yellow-100 bg-yellow-50 px-3 py-2 break-words">
                                    <span class="font-semibold">Fila {{ $warning['row'] ?? '-' }}</span>
                                    <div class="mt-1 text-xs text-yellow-700">{{ $warning['message'] ?? 'Advertencia de importación.' }}</div>
                                </li>
                            @endforeach
                        </ul>

                        @if(($utmCsvImportResult['warnings_remaining'] ?? 0) > 0)
                            <p class="mt-3 text-xs text-yellow-600">
                                Se omitieron {{ $utmCsvImportResult['warnings_remaining'] }} advertencias adicionales.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
