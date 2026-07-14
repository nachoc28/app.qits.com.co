<div class="space-y-6">
    @if (session()->has('ai_flow_execution_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="font-semibold">Éxito:</span> {{ session('ai_flow_execution_success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <h3 class="text-base font-semibold text-cyan-900">Ejecuciones de Flujos IA</h3>
        <p class="mt-1 text-sm text-cyan-800">
            Esta fase muestra ejecuciones creadas y su acceso al detalle base por etapas.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">Flujo</th>
                        <th class="px-4 py-3">Versión usada</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Fecha de inicio</th>
                        <th class="px-4 py-3">Usuario creador</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($executions as $execution)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $execution->title }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($execution->empresa)->nombre ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($execution->flow)->name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                @if($execution->version)
                                    Versión {{ $execution->version->version_number }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                    {{ $statusLabels[$execution->status] ?? $execution->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($execution->started_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($execution->startedBy)->name ?: '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('admin.ai-flow-executions.show', $execution) }}"
                                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                                >
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                Todavía no hay ejecuciones de Flujos IA.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $executions->links() }}
    </div>
</div>
