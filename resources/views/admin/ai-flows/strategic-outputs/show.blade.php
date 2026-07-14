<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Detalle de resultado estrategico
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Consulta completa del activo marcado para el cliente.
                </p>
            </div>

            <a
                href="{{ route('admin.ai-flow-strategic-outputs.index') }}"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            >
                Volver a resultados
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
                <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                    <div>
                        <dt class="font-medium text-gray-500">Tipo</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $typeLabel }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Empresa</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional($output->empresa)->nombre ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Titulo</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $output->title }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Vigente</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $output->is_current ? 'Si' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Flujo</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional(optional($output->execution)->flow)->name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Ejecucion</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional($output->execution)->title ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Etapa origen</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional(optional($output->executionStep)->step)->name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Marcado por</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional($output->markedBy)->name ?: '-' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Fecha</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ optional($output->marked_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
                <h3 class="font-semibold text-gray-900">Contenido completo</h3>
                <div class="mt-4 whitespace-pre-wrap rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-800">{{ $output->content }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
