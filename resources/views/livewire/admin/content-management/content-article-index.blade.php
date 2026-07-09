<div class="space-y-6">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm ring-1 ring-amber-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-amber-900">Listado principal de artículos</h3>
                <p class="mt-1 text-sm text-amber-800">
                    La prioridad visual y operativa resalta primero lo que sigue en procesamiento y luego los artículos pendientes del periodo actual.
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_180px_180px_140px]">
            <div class="min-w-0">
                <label for="content_articles_search" class="text-sm font-medium text-gray-700">Búsqueda</label>
                <input
                    id="content_articles_search"
                    type="text"
                    wire:model.debounce.400ms="search"
                    placeholder="Tema o empresa"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            <div class="min-w-0">
                <label for="content_articles_empresa" class="text-sm font-medium text-gray-700">Empresa</label>
                <select
                    id="content_articles_empresa"
                    wire:model="selectedEmpresaId"
                    @if(! $hasMultipleEmpresas) disabled @endif
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                >
                    <option value="">Todas</option>
                    @foreach($authorizedEmpresas as $empresaOption)
                        <option value="{{ $empresaOption['id'] }}">{{ $empresaOption['nombre'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label for="content_articles_status" class="text-sm font-medium text-gray-700">Estado principal</label>
                <select
                    id="content_articles_status"
                    wire:model="selectedMainStatus"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Todos</option>
                    @foreach($mainStatusOptions as $statusOption)
                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label for="content_articles_period" class="text-sm font-medium text-gray-700">Periodo</label>
                <select
                    id="content_articles_period"
                    wire:model="selectedPeriod"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    @foreach($periodOptions as $periodValue => $periodLabel)
                        <option value="{{ $periodValue }}">{{ $periodLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0">
                <label for="content_articles_per_page" class="text-sm font-medium text-gray-700">Paginación</label>
                <select
                    id="content_articles_per_page"
                    wire:model="perPage"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2 text-xs text-gray-600 lg:grid-cols-4">
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2">
                <span class="font-semibold text-yellow-800">En proceso</span>
                <span class="block mt-1">Prioridad 1. Requiere continuidad inmediata.</span>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                <span class="font-semibold text-blue-800">Pendiente mes actual</span>
                <span class="block mt-1">Prioridad 2. Sigue el calendario operativo vigente.</span>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                <span class="font-semibold text-gray-700">Pendiente fuera del mes</span>
                <span class="block mt-1">Prioridad 3. Visible pero fuera del foco inmediato.</span>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                <span class="font-semibold text-green-800">Publicado</span>
                <span class="block mt-1">Prioridad 4. Se ordena por publicación más reciente.</span>
            </div>
        </div>
    </div>

    <div class="hidden overflow-visible rounded-2xl border border-gray-200 bg-white shadow-sm sm:block">
        <div class="overflow-x-auto">
            <table class="min-w-[1320px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Tema</th>
                        <th class="px-4 py-3">Estado principal</th>
                        <th class="px-4 py-3">Etapa operativa</th>
                        <th class="px-4 py-3">Entregado</th>
                        <th class="px-4 py-3">Publicado</th>
                        <th class="px-4 py-3">Última actualización</th>
                        <th class="px-4 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($articles as $article)
                        @php
                            $isProcessing = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_PROCESSING;
                            $isPublished = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_PUBLISHED;
                            $isCurrentMonthUnpublished = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_UNPUBLISHED
                                && optional($article->article_date)->isSameMonth(now(), false);
                            $cardClass = $isProcessing
                                ? 'bg-yellow-50'
                                : ($isCurrentMonthUnpublished
                                    ? 'bg-blue-50'
                                    : ($isPublished ? 'bg-green-50' : 'bg-white'));
                            $statusLabel = $isProcessing
                                ? 'En proceso'
                                : ($isCurrentMonthUnpublished
                                    ? 'Pendiente mes actual'
                                    : ($isPublished ? 'Publicado' : 'Pendiente'));
                            $statusClass = $isProcessing
                                ? 'border-yellow-200 bg-yellow-100 text-yellow-800'
                                : ($isCurrentMonthUnpublished
                                    ? 'border-blue-200 bg-blue-100 text-blue-800'
                                    : ($isPublished
                                        ? 'border-green-200 bg-green-100 text-green-800'
                                        : 'border-gray-200 bg-gray-100 text-gray-700'));
                            $actionLabel = $article->operational_stage === \App\Models\ContentArticle::STAGE_PENDING ? 'Generar' : 'Continuar';
                        @endphp
                        <tr class="{{ $cardClass }} align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $article->contentImport->empresa->nombre }}</div>
                                <div class="mt-1 text-xs text-gray-500">Importación: {{ $article->contentImport->import_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($article->article_date)->format('Y-m-d') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="max-w-md break-words font-medium text-gray-900">{{ $article->topic }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                                <div class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ $article->main_status }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ str_replace('_', ' ', $article->operational_stage) }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($article->delivered_at)
                                    <span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-800">Sí</span>
                                    <div class="mt-1 text-xs text-gray-500">{{ $article->delivered_at->format('Y-m-d H:i') }}</div>
                                @else
                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($article->published_at)
                                    <span class="inline-flex rounded-full border border-green-200 bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Sí</span>
                                    <div class="mt-1 text-xs text-gray-500">{{ $article->published_at->format('Y-m-d H:i') }}</div>
                                @else
                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($article->updated_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.content-management.articles.show', $article) }}"
                                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                                >
                                    {{ $actionLabel }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                No hay artículos que coincidan con la búsqueda y filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-3 sm:hidden">
        @forelse($articles as $article)
            @php
                $isProcessing = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_PROCESSING;
                $isPublished = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_PUBLISHED;
                $isCurrentMonthUnpublished = $article->main_status === \App\Models\ContentArticle::MAIN_STATUS_UNPUBLISHED
                    && optional($article->article_date)->isSameMonth(now(), false);
                $cardClass = $isProcessing
                    ? 'border-yellow-200 bg-yellow-50'
                    : ($isCurrentMonthUnpublished
                        ? 'border-blue-200 bg-blue-50'
                        : ($isPublished ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'));
                $statusLabel = $isProcessing
                    ? 'En proceso'
                    : ($isCurrentMonthUnpublished
                        ? 'Pendiente mes actual'
                        : ($isPublished ? 'Publicado' : 'Pendiente'));
                $actionLabel = $article->operational_stage === \App\Models\ContentArticle::STAGE_PENDING ? 'Generar' : 'Continuar';
            @endphp
            <div class="rounded-2xl border p-4 shadow-sm {{ $cardClass }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900">{{ $article->contentImport->empresa->nombre }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ optional($article->article_date)->format('Y-m-d') ?: '-' }}</div>
                    </div>

                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $isProcessing ? 'border-yellow-200 bg-yellow-100 text-yellow-800' : ($isCurrentMonthUnpublished ? 'border-blue-200 bg-blue-100 text-blue-800' : ($isPublished ? 'border-green-200 bg-green-100 text-green-800' : 'border-gray-200 bg-gray-100 text-gray-700')) }}">
                        {{ $statusLabel }}
                    </span>
                </div>

                <div class="mt-3 break-words text-sm font-medium text-gray-900">{{ $article->topic }}</div>

                <div class="mt-4 space-y-2 text-sm text-gray-700">
                    <div><span class="font-medium">Estado:</span> {{ $article->main_status }}</div>
                    <div><span class="font-medium">Etapa:</span> {{ str_replace('_', ' ', $article->operational_stage) }}</div>
                    <div><span class="font-medium">Entregado:</span> {{ $article->delivered_at ? 'Sí' : 'No' }}</div>
                    <div><span class="font-medium">Publicado:</span> {{ $article->published_at ? 'Sí' : 'No' }}</div>
                    <div><span class="font-medium">Actualizado:</span> {{ optional($article->updated_at)->format('Y-m-d H:i') ?: '-' }}</div>
                </div>

                <div class="mt-4">
                    <a
                        href="{{ route('admin.content-management.articles.show', $article) }}"
                        class="inline-flex w-full items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                    >
                        {{ $actionLabel }}
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                No hay artículos que coincidan con la búsqueda y filtros aplicados.
            </div>
        @endforelse
    </div>

    <div>
        {{ $articles->links() }}
    </div>
</div>
