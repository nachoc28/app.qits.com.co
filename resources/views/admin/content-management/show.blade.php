<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestion de Contenidos - Detalle Operativo
                </h2>
                <p class="mt-1 max-w-2xl rounded-lg bg-white/15 px-3 py-1.5 text-sm font-medium text-emerald-950 sm:px-0 sm:py-0 sm:bg-transparent sm:text-emerald-950">
                    Detalle operativo del articulo para avanzar los pasos habilitados del flujo de contenidos.
                </p>
            </div>

            <a
                href="{{ route('admin.content-management.index') }}"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            >
                Volver al listado
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleObjectiveDetail::class, ['articleId' => $article->id])
        </div>
    </div>
</x-app-layout>
