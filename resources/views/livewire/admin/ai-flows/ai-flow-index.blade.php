<div class="space-y-6">
    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <h3 class="text-base font-semibold text-cyan-900">Flujos IA configurados</h3>
        <p class="mt-1 text-sm text-cyan-800">
            Esta fase permite administrar el registro principal y sus versiones. El constructor de etapas se implementará después.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Key</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Versión publicada</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($flows as $flow)
                        @php
                            $publishedVersion = $flow->versions->firstWhere('status', \App\Models\AiFlowVersion::STATUS_PUBLISHED);
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">{{ $flow->name }}</div>
                                @if($flow->description)
                                    <div class="mt-1 max-w-md text-xs text-gray-500">{{ $flow->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $flow->key }}</td>
                            <td class="px-4 py-3 text-gray-700">-</td>
                            <td class="px-4 py-3">
                                @if($flow->is_active)
                                    <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Activo</span>
                                @else
                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                @if($publishedVersion)
                                    Versión {{ $publishedVersion->version_number }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col justify-end gap-2 sm:flex-row">
                                    <a
                                        href="{{ route('admin.ai-flows.edit', $flow) }}"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                    >
                                        Editar
                                    </a>
                                    <a
                                        href="{{ route('admin.ai-flows.versions.index', $flow) }}"
                                        class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                                    >
                                        Ver versiones
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                Todavía no hay flujos IA configurados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $flows->links() }}
    </div>
</div>
