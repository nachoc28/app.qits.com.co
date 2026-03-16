<div class="space-y-6">
    @if($configurationStatus === 'not_configured')
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
            <h3 class="text-base font-semibold text-blue-900">SEO no configurado</h3>
            <p class="mt-1 text-sm text-blue-800">Esta empresa aún no tiene configuración SEO. Puedes configurarla para habilitar el dashboard.</p>

            <div class="mt-4">
                <a href="{{ route('admin.seo.empresa-settings', $empresa) }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Configurar SEO
                </a>
            </div>
        </div>
    @elseif($configurationStatus === 'partially_configured')
        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5 sm:p-6">
            <h3 class="text-base font-semibold text-yellow-900">Configuración SEO incompleta</h3>
            <p class="mt-1 text-sm text-yellow-800">Hay campos pendientes. Completa la configuración para habilitar todas las métricas del dashboard.</p>

            @if(!empty($statusErrors))
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-yellow-900">
                    @foreach($statusErrors as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            @endif

            @if(!empty($statusWarnings))
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-yellow-800">
                    @foreach($statusWarnings as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4">
                <a href="{{ route('admin.seo.empresa-settings', $empresa) }}"
                   class="inline-flex items-center rounded-md bg-yellow-600 px-4 py-2 text-sm font-semibold text-white hover:bg-yellow-700">
                    Completar configuración SEO
                </a>
            </div>
        </div>
    @endif

    @if($canShowDashboard)
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="min-w-0">
                <label class="text-sm font-medium text-gray-700">Desde</label>
                <input type="date" wire:model.defer="dateFrom" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('dateFrom') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="min-w-0">
                <label class="text-sm font-medium text-gray-700">Hasta</label>
                <input type="date" wire:model.defer="dateTo" class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('dateTo') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="min-w-0 md:col-span-2 flex items-end">
                <button type="button" class="w-full sm:w-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" wire:click="applyFilters">
                    Aplicar filtros
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-7">
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Clicks orgánicos</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((int)($kpis['organic_clicks'] ?? 0)) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Impresiones</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((int)($kpis['impressions'] ?? 0)) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">CTR promedio</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((float)($kpis['avg_ctr'] ?? 0), 4) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Posición promedio</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((float)($kpis['avg_position'] ?? 0), 2) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Usuarios</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((int)($kpis['users'] ?? 0)) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Sesiones</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((int)($kpis['sessions'] ?? 0)) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="text-xs text-gray-500">Conversiones UTM</div>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format((int)($kpis['total_utm_conversions'] ?? 0)) }}</div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <h3 class="text-sm font-semibold text-gray-900">Series de tendencia</h3>
        <p class="mt-1 text-xs text-gray-500">Datos listos para gráficos en Livewire/JS.</p>

        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="text-xs font-medium text-gray-600">GSC (clicks, impresiones, CTR, posición)</div>
                <div class="mt-2 text-xs text-gray-700 break-words">{{ implode(', ', array_slice((array) data_get($trends, 'gsc.clicks', []), 0, 12)) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="text-xs font-medium text-gray-600">GA4 (users, sessions, conversions)</div>
                <div class="mt-2 text-xs text-gray-700 break-words">{{ implode(', ', array_slice((array) data_get($trends, 'ga4.sessions', []), 0, 12)) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-3">
                <div class="text-xs font-medium text-gray-600">UTM conversions</div>
                <div class="mt-2 text-xs text-gray-700 break-words">{{ implode(', ', array_slice((array) data_get($trends, 'utm.conversions', []), 0, 12)) }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <h3 class="text-base font-semibold text-gray-900">Términos de búsqueda con mejor ranking</h3>
        <p class="mt-1 text-sm text-gray-500">Consulta, posición promedio, clicks, impresiones y CTR.</p>

        <div class="mt-4 hidden sm:block rounded-xl border border-gray-200 overflow-x-auto">
            <table class="min-w-[900px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Término</th>
                        <th class="px-4 py-3 text-right">Posición prom.</th>
                        <th class="px-4 py-3 text-right">Clicks</th>
                        <th class="px-4 py-3 text-right">Impresiones</th>
                        <th class="px-4 py-3 text-right">CTR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topQueries as $row)
                        <tr>
                            <td class="px-4 py-3 break-words">{{ $row['query'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float)($row['avg_position'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((int)($row['clicks'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((int)($row['impressions'] ?? 0)) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float)($row['avg_ctr'] ?? 0), 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin datos de queries en el rango</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 space-y-3 sm:hidden">
            @forelse($topQueries as $row)
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="font-medium text-gray-900 break-words">{{ $row['query'] ?? '-' }}</div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-gray-700">
                        <div><span class="font-medium">Posición:</span> {{ number_format((float)($row['avg_position'] ?? 0), 2) }}</div>
                        <div><span class="font-medium">Clicks:</span> {{ number_format((int)($row['clicks'] ?? 0)) }}</div>
                        <div><span class="font-medium">Impresiones:</span> {{ number_format((int)($row['impressions'] ?? 0)) }}</div>
                        <div><span class="font-medium">CTR:</span> {{ number_format((float)($row['avg_ctr'] ?? 0), 4) }}</div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Sin datos de queries en el rango</div>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900">Top landing pages</h3>
            <div class="mt-4 space-y-3">
                @forelse($topLandingPages as $row)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-sm font-medium text-gray-900 break-words">{{ $row['landing_page'] ?? '-' }}</div>
                        <div class="mt-1 grid grid-cols-2 gap-2 text-xs text-gray-700">
                            <div>Users: {{ number_format((int)($row['users'] ?? 0)) }}</div>
                            <div>Sessions: {{ number_format((int)($row['sessions'] ?? 0)) }}</div>
                            <div>Conversions: {{ number_format((int)($row['conversions'] ?? 0)) }}</div>
                            <div>Engagement: {{ number_format((float)($row['engagement_rate'] ?? 0), 4) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Sin landing pages en el rango</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900">Conversiones UTM recientes</h3>
            <div class="mt-4 space-y-3">
                @forelse($recentUtmConversions as $row)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-sm font-medium text-gray-900">{{ $row['conversion_datetime'] ?? '-' }}</div>
                        <div class="mt-1 text-xs text-gray-700 break-words">{{ $row['event_name'] ?? 'event' }} | {{ $row['source'] ?? '-' }}/{{ $row['medium'] ?? '-' }}</div>
                        <div class="mt-1 text-xs text-gray-600 break-words">{{ $row['page_url'] ?? '-' }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">Sin conversiones UTM en el rango</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
