<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Versiones de Flujo IA
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $flow->name }}
                </p>
            </div>

            <a
                href="{{ route('admin.ai-flows.index') }}"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            >
                Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @livewire(\App\Http\Livewire\Admin\AiFlows\AiFlowVersionIndex::class, ['flowId' => $flow->id])
        </div>
    </div>
</x-app-layout>
