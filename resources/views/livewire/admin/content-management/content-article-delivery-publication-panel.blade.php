<div class="space-y-6">
    @if (session()->has('content_release_success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            {{ session('content_release_success') }}
        </div>
    @endif

    @error('delivery')
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
            {{ $message }}
        </div>
    @enderror

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Entrega manual</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        La entrega se registra como evento manual independiente de la publicacion.
                    </p>
                </div>

                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $article->delivered_at ? 'border-blue-200 bg-blue-100 text-blue-800' : 'border-gray-200 bg-gray-100 text-gray-700' }}">
                    {{ $article->delivered_at ? 'Entregado' : 'No entregado' }}
                </span>
            </div>

            @if (! $deliveryAvailability['allowed'])
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ $deliveryAvailability['message'] }}
                </div>
            @endif

            <dl class="mt-5 space-y-3 text-sm text-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-gray-600">Fecha</dt>
                    <dd class="text-right">{{ optional($article->delivered_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-gray-600">Usuario</dt>
                    <dd class="text-right">{{ optional($article->deliveredBy)->name ?: '-' }}</dd>
                </div>
            </dl>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <button
                    type="button"
                    wire:click="markDelivered"
                    @if(! $deliveryAvailability['allowed']) disabled @endif
                    class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500"
                >
                    Marcar entregado
                </button>

                <button
                    type="button"
                    wire:click="unmarkDelivered"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Desmarcar entrega
                </button>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Publicacion manual</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        La publicacion se registra aparte y no depende automaticamente de la entrega.
                    </p>
                </div>

                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $article->published_at ? 'border-green-200 bg-green-100 text-green-800' : 'border-gray-200 bg-gray-100 text-gray-700' }}">
                    {{ $article->published_at ? 'Publicado' : 'No publicado' }}
                </span>
            </div>

            <dl class="mt-5 space-y-3 text-sm text-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-gray-600">Fecha</dt>
                    <dd class="text-right">{{ optional($article->published_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-gray-600">Usuario</dt>
                    <dd class="text-right">{{ optional($article->publishedBy)->name ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-gray-600">URL publicada</dt>
                    <dd class="max-w-sm break-all text-right">
                        @if($article->published_url)
                            <a href="{{ $article->published_url }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-700">
                                {{ $article->published_url }}
                            </a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-5">
                <label for="content_published_url" class="text-sm font-medium text-gray-700">URL publicada</label>
                <input
                    id="content_published_url"
                    type="url"
                    wire:model.defer="publishedUrl"
                    class="mt-2 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="https://ejemplo.com/articulo-publicado"
                >
                @error('publishedUrl')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <button
                    type="button"
                    wire:click="publishArticle"
                    class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100"
                >
                    Publicar
                </button>

                <button
                    type="button"
                    wire:click="updatePublishedUrlAction"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Actualizar URL publicada
                </button>
            </div>
        </div>
    </div>
</div>
