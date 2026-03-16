<div class="py-6">
    @if (session()->has('message'))
        <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
            {{ session('message') }}
        </div>
    @endif

    <div class="space-y-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_120px] lg:w-full lg:max-w-2xl">
                <input
                    type="text"
                    wire:model.debounce.400ms="search"
                    placeholder="Buscar por nombre, NIT o email"
                    class="w-full min-w-0 rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <select
                    wire:model="perPage"
                    class="w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    title="Items por página"
                >
                    <option value="10">10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>

            <div class="flex w-full lg:w-auto">
                <x-jet-button class="w-full justify-center lg:w-auto" wire:click="new">
                    + Nueva empresa
                </x-jet-button>
            </div>
        </div>

        {{-- Desktop table --}}
        <div class="hidden sm:block overflow-visible rounded-xl border border-gray-200">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr class="text-left">
                            <th class="px-4 py-3">Logo</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">NIT</th>
                            <th class="px-4 py-3">Ciudad</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($empresas as $e)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    @if($e->logo)
                                        <img
                                            src="{{ asset('storage/'.$e->logo) }}"
                                            alt="logo"
                                            class="h-10 w-10 rounded-lg object-cover ring-1 ring-gray-200"
                                        >
                                    @else
                                        <div class="h-10 w-10 rounded-lg bg-gray-200 ring-1 ring-gray-200"></div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 font-medium text-gray-900 break-words">
                                    {{ $e->nombre }}
                                    <div class="mt-1">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $e->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $e->active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 break-words">{{ $e->nit ?: '-' }}</td>

                                <td class="px-4 py-3 break-words">
                                    {{ $e->ciudad->nombre ?? '-' }}
                                    @if(optional($e->ciudad->departamento)->nombre)
                                        <div class="text-xs text-gray-500">
                                            {{ optional($e->ciudad->departamento)->nombre }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 break-words">{{ $e->email ?: '-' }}</td>
                                <td class="px-4 py-3 break-words">{{ $e->telefono ?: '-' }}</td>

                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            href="{{ route('admin.empresas.seo-entry', $e) }}"
                                            class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                                        >
                                            SEO
                                        </a>

                                        <div
                                        class="inline-block"
                                        x-data="{
                                            open: false,
                                            top: 0,
                                            left: 0,
                                            panelWidth: 192,
                                            updatePosition() {
                                                const rect = this.$refs.trigger.getBoundingClientRect();
                                                this.top = rect.bottom + 8;
                                                const rightAligned = rect.right - this.panelWidth;
                                                this.left = Math.max(8, Math.min(rightAligned, window.innerWidth - this.panelWidth - 8));
                                            },
                                            toggle() {
                                                this.open = !this.open;
                                                if (this.open) {
                                                    this.$nextTick(() => this.updatePosition());
                                                }
                                            }
                                        }"
                                        @keydown.escape.window="open = false"
                                        @resize.window="open && updatePosition()"
                                        @scroll.window="open && updatePosition()"
                                    >
                                        <button
                                            x-ref="trigger"
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50"
                                            @click="toggle()"
                                        >
                                            Acciones
                                            <svg class="ml-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                            </svg>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="open" x-cloak class="fixed inset-0 z-40" @click="open = false">
                                                <div
                                                    class="fixed z-50 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5"
                                                    :style="`top:${top}px; left:${left}px`"
                                                    @click.stop
                                                >
                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50" onclick="@this.call('show', {{ $e->id }})" @click="open = false">
                                                        Ver
                                                    </button>

                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50" onclick="@this.call('edit', {{ $e->id }})" @click="open = false">
                                                        Editar
                                                    </button>

                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50" onclick="@this.call('openToggle', {{ $e->id }})" @click="open = false">
                                                        {{ $e->active ? 'Inactivar' : 'Activar' }}
                                                    </button>

                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50" onclick="@this.call('openUsers', {{ $e->id }})" @click="open = false">
                                                        Usuarios
                                                    </button>

                                                    <button type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50" onclick="@this.call('openServices', {{ $e->id }})" @click="open = false">
                                                        Servicios
                                                    </button>

                                                    <div class="my-1 border-t border-gray-100"></div>

                                                    <button
                                                        type="button"
                                                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                                        onclick="if(confirm('¿Eliminar empresa?')) @this.call('destroy', {{ $e->id }})"
                                                        @click="open = false"
                                                    >
                                                        Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10">
                                    <div class="text-center text-gray-500">
                                        <div class="text-sm">Sin resultados</div>
                                        <div class="mt-3">
                                            <x-jet-secondary-button wire:click="new">
                                                Crear la primera empresa
                                            </x-jet-secondary-button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile cards --}}
        <div class="space-y-3 sm:hidden">
            @forelse($empresas as $e)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0">
                            @if($e->logo)
                                <img
                                    src="{{ asset('storage/'.$e->logo) }}"
                                    alt="logo"
                                    class="h-12 w-12 rounded-lg object-cover ring-1 ring-gray-200"
                                >
                            @else
                                <div class="h-12 w-12 rounded-lg bg-gray-200 ring-1 ring-gray-200"></div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="break-words font-semibold text-gray-900">{{ $e->nombre }}</div>
                            <div class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $e->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $e->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-gray-700">
                        <div><span class="font-medium">NIT:</span> {{ $e->nit ?: '-' }}</div>
                        <div class="break-words">
                            <span class="font-medium">Ciudad:</span>
                            {{ $e->ciudad->nombre ?? '-' }}
                            @if(optional($e->ciudad->departamento)->nombre)
                                - {{ optional($e->ciudad->departamento)->nombre }}
                            @endif
                        </div>
                        <div class="break-words"><span class="font-medium">Email:</span> {{ $e->email ?: '-' }}</div>
                        <div class="break-words"><span class="font-medium">Teléfono:</span> {{ $e->telefono ?: '-' }}</div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a
                            href="{{ route('admin.empresas.seo-entry', $e) }}"
                            class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-center text-sm font-medium text-indigo-700"
                        >
                            SEO
                        </a>

                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            wire:click="show({{ $e->id }})"
                        >
                            Ver
                        </button>

                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            wire:click="edit({{ $e->id }})"
                        >
                            Editar
                        </button>

                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            wire:click="openToggle({{ $e->id }})"
                        >
                            {{ $e->active ? 'Inactivar' : 'Activar' }}
                        </button>

                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            wire:click="openUsers({{ $e->id }})"
                        >
                            Usuarios
                        </button>

                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                            wire:click="openServices({{ $e->id }})"
                        >
                            Servicios
                        </button>

                        <button
                            type="button"
                            class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-600"
                            onclick="if(confirm('¿Eliminar empresa?')) @this.call('destroy', {{ $e->id }})"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500">
                    Sin resultados
                    <div class="mt-3">
                        <x-jet-secondary-button wire:click="new">
                            Crear la primera empresa
                        </x-jet-secondary-button>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="pt-2">
            <div class="flex justify-end">
                {{ $empresas->links() }}
            </div>
        </div>
    </div>

    {{-- Modal crear/editar empresa --}}
    <x-jet-dialog-modal wire:model="showModal" maxWidth="2xl">
        <x-slot name="title">
            {{ $isViewing ? 'Detalle de empresa' : ($editingId ? 'Editar empresa' : 'Nueva empresa') }}
        </x-slot>

        <x-slot name="content">
            <div class="max-h-[75vh] overflow-y-auto overflow-x-hidden pr-1">
                <fieldset @if($isViewing) disabled @endif>
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        {{-- Columna principal --}}
                        <div class="space-y-5 lg:col-span-2">
                            <div class="min-w-0">
                                <x-jet-label value="Nombre" />
                                <x-jet-input type="text" class="w-full" wire:model.defer="empresa.nombre" />
                                <x-jet-input-error for="empresa.nombre" />
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="min-w-0">
                                    <x-jet-label value="NIT" />
                                    <x-jet-input type="text" class="w-full" wire:model.defer="empresa.nit" />
                                    <x-jet-input-error for="empresa.nit" />
                                </div>

                                <div class="min-w-0">
                                    <x-jet-label value="Email" />
                                    <x-jet-input type="email" class="w-full" wire:model.defer="empresa.email" />
                                    <x-jet-input-error for="empresa.email" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <x-jet-label value="Dirección" />
                                <x-jet-input type="text" class="w-full" wire:model.defer="empresa.direccion" />
                                <x-jet-input-error for="empresa.direccion" />
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <div class="min-w-0">
                                    <x-jet-label value="País" />
                                    <select
                                        class="w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        wire:model="pais_id"
                                    >
                                        <option value="">Seleccione...</option>
                                        @foreach($paises as $p)
                                            <option value="{{ $p['id'] }}">{{ $p['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('pais_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                                </div>

                                <div class="min-w-0">
                                    <x-jet-label value="Departamento/Estado" />
                                    <select
                                        class="w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        wire:model="departamento_id"
                                    >
                                        <option value="">Seleccione...</option>
                                        @foreach($departamentos as $d)
                                            <option value="{{ $d['id'] }}">{{ $d['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('departamento_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                                </div>

                                <div class="min-w-0">
                                    <x-jet-label value="Ciudad/Municipio" />
                                    <select
                                        class="w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        wire:model.defer="empresa.ciudad_id"
                                    >
                                        <option value="">Seleccione...</option>
                                        @foreach($ciudades as $c)
                                            <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                    <x-jet-input-error for="empresa.ciudad_id" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="min-w-0">
                                    <x-jet-label value="Teléfono" />
                                    <x-jet-input type="text" class="w-full" wire:model.defer="empresa.telefono" />
                                    <x-jet-input-error for="empresa.telefono" />
                                </div>
                            </div>
                        </div>

                        {{-- Columna lateral --}}
                        <div class="space-y-5">
                            <div class="rounded-xl border border-gray-200 p-4">
                                <x-jet-label value="Logo" />

                                @if(!$isViewing)
                                    <input type="file" wire:model="logoFile" class="mt-1 block w-full text-sm text-gray-700">
                                    <x-jet-input-error for="logoFile" />
                                @endif

                                <div class="mt-3 flex min-h-[120px] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 p-3">
                                    @if ($logoFile)
                                        <img src="{{ $logoFile->temporaryUrl() }}" class="max-h-28 rounded object-contain shadow" alt="Preview logo">
                                    @elseif(!empty($empresa['logo']))
                                        <img src="{{ asset('storage/'.$empresa['logo']) }}" class="max-h-28 rounded object-contain shadow" alt="Logo empresa">
                                    @else
                                        <span class="text-sm text-gray-400">Sin logo cargado</span>
                                    @endif
                                </div>

                                <div wire:loading wire:target="logoFile" class="mt-2 text-xs text-gray-500">
                                    Subiendo archivo...
                                </div>
                            </div>

                            @if(!$isViewing)
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" wire:model="createUser" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="text-sm font-medium text-gray-700">Crear usuario Cliente</span>
                                    </label>

                                    @if($createUser)
                                        <div class="mt-4 space-y-4">
                                            <div class="min-w-0">
                                                <x-jet-label value="Nombre del usuario" />
                                                <x-jet-input type="text" class="w-full" wire:model.defer="user_name" />
                                                <x-jet-input-error for="user_name" />
                                            </div>

                                            <div class="min-w-0">
                                                <x-jet-label value="Email del usuario" />
                                                <x-jet-input type="email" class="w-full" wire:model.defer="user_email" />
                                                <x-jet-input-error for="user_email" />
                                            </div>

                                            <div class="min-w-0">
                                                <x-jet-label value="Teléfono del usuario" />
                                                <x-jet-input type="text" class="w-full" wire:model.defer="user_phone" />
                                            </div>

                                            <div class="min-w-0">
                                                <x-jet-label value="Contraseña" />
                                                <x-jet-input
                                                    type="text"
                                                    class="w-full"
                                                    wire:model.defer="user_password"
                                                    placeholder="Mínimo 8 caracteres"
                                                />
                                                <x-jet-input-error for="user_password" />
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </fieldset>
            </div>
        </x-slot>

        <x-slot name="footer">
            @if($isViewing)
                <x-jet-secondary-button wire:click="$set('showModal', false)">
                    Cerrar
                </x-jet-secondary-button>
            @else
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-jet-secondary-button class="w-full justify-center sm:w-auto" wire:click="$set('showModal', false)">
                        Cancelar
                    </x-jet-secondary-button>

                    <x-jet-button class="w-full justify-center sm:w-auto" wire:click="{{ $editingId ? 'update' : 'store' }}">
                        Guardar
                    </x-jet-button>
                </div>
            @endif
        </x-slot>
    </x-jet-dialog-modal>

    {{-- Modal activar / inactivar empresa --}}
    <x-jet-dialog-modal wire:model="showToggleModal" maxWidth="md">
        <x-slot name="title">
            {{ $toggleTargetActive ? 'Activar empresa' : 'Inactivar empresa' }}
        </x-slot>

        <x-slot name="content">
            <div class="space-y-2">
                @if($toggleTargetActive)
                    <p>¿Deseas <strong>activar</strong> esta empresa? Sus usuarios podrán acceder y sus integraciones se reanudarán.</p>
                @else
                    <p>¿Deseas <strong>inactivar</strong> esta empresa?</p>
                    <ul class="ml-5 list-disc text-sm text-gray-600">
                        <li>Sus usuarios no podrán acceder al sistema.</li>
                        <li>Las integraciones deberán dejar de sincronizar.</li>
                    </ul>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$set('showToggleModal', false)">
                Cancelar
            </x-jet-secondary-button>

            <x-jet-button class="ml-2" wire:click="confirmToggle">
                {{ $toggleTargetActive ? 'Activar' : 'Inactivar' }}
            </x-jet-button>
        </x-slot>
    </x-jet-dialog-modal>

    {{-- Modal usuarios --}}
    <x-jet-dialog-modal wire:model="showUsersModal" maxWidth="2xl">
        <x-slot name="title">
            <div class="pr-6">
                <div class="font-semibold">Usuarios</div>
                <div class="break-words text-sm text-gray-500">{{ $usersEmpresaNombre }}</div>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="max-h-[75vh] space-y-4 overflow-y-auto overflow-x-hidden pr-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <input
                        type="text"
                        wire:model.debounce.400ms="usersSearch"
                        class="w-full rounded-md border-gray-300 px-3 py-2 shadow-sm sm:max-w-md focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Buscar por nombre, email o teléfono"
                    />

                    <x-jet-secondary-button class="w-full justify-center sm:w-auto" wire:click="newUser">
                        + Nuevo usuario
                    </x-jet-secondary-button>
                </div>

                {{-- Desktop usuarios --}}
                <div class="hidden sm:block overflow-hidden rounded-xl border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-[900px] w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left">
                                    <th class="px-3 py-2">Nombre</th>
                                    <th class="px-3 py-2">Email</th>
                                    <th class="px-3 py-2">Teléfono</th>
                                    <th class="px-3 py-2">Tipo</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($users as $u)
                                    <tr>
                                        <td class="px-3 py-3 break-words">{{ $u['name'] }}</td>
                                        <td class="px-3 py-3 break-words">{{ $u['email'] }}</td>
                                        <td class="px-3 py-3 break-words">{{ $u['telefono'] ?? '-' }}</td>
                                        <td class="px-3 py-3 break-words">{{ $u['tipo_usuario']['nombre'] ?? '-' }}</td>
                                        <td class="px-3 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs {{ ($u['active'] ?? true) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                                {{ ($u['active'] ?? true) ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50"
                                                    wire:click="editUser({{ $u['id'] }})"
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm hover:bg-gray-50"
                                                    wire:click="openToggleUser({{ $u['id'] }})"
                                                >
                                                    {{ ($u['active'] ?? true) ? 'Inactivar' : 'Activar' }}
                                                </button>

                                                <button
                                                    type="button"
                                                    class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                                                    wire:click="openDeleteUser({{ $u['id'] }})"
                                                >
                                                    Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                            Sin usuarios
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Mobile usuarios --}}
                <div class="space-y-3 sm:hidden">
                    @forelse($users as $u)
                        <div class="rounded-xl border border-gray-200 p-4 shadow-sm">
                            <div class="font-semibold break-words">{{ $u['name'] }}</div>
                            <div class="mt-1 break-words text-sm text-gray-600">{{ $u['email'] }}</div>

                            <div class="mt-3 space-y-1 text-sm text-gray-700">
                                <div><span class="font-medium">Tel:</span> {{ $u['telefono'] ?? '-' }}</div>
                                <div><span class="font-medium">Tipo:</span> {{ $u['tipo_usuario']['nombre'] ?? '-' }}</div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span class="rounded-full px-2 py-1 text-xs {{ ($u['active'] ?? true) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ ($u['active'] ?? true) ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                    wire:click="editUser({{ $u['id'] }})"
                                >
                                    Editar
                                </button>

                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                    wire:click="openToggleUser({{ $u['id'] }})"
                                >
                                    {{ ($u['active'] ?? true) ? 'Inactivar' : 'Activar' }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm text-red-600"
                                    wire:click="openDeleteUser({{ $u['id'] }})"
                                >
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-gray-500">Sin usuarios</div>
                    @endforelse
                </div>

                {{-- Formulario usuario --}}
                <div class="rounded-xl border border-gray-200 p-4 space-y-4">
                    <div class="font-semibold">
                        {{ $userEditingId ? 'Editar usuario' : 'Crear usuario' }}
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="min-w-0">
                            <label class="break-words text-sm font-medium text-gray-700">Nombre</label>
                            <input
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="text"
                                wire:model.defer="userEmpresa.name"
                            >
                            @error('userEmpresa.name') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="min-w-0">
                            <label class="break-words text-sm font-medium text-gray-700">Email</label>
                            <input
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="email"
                                wire:model.defer="userEmpresa.email"
                            >
                            @error('userEmpresa.email') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="min-w-0">
                            <label class="break-words text-sm font-medium text-gray-700">Teléfono</label>
                            <input
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="text"
                                wire:model.defer="userEmpresa.telefono"
                            >
                            @error('userEmpresa.telefono') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="min-w-0">
                            <label class="break-words text-sm font-medium text-gray-700">Tipo usuario</label>
                            <select
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                wire:model.defer="userEmpresa.tipo_usuario_id"
                            >
                                <option value="">-- Seleccionar --</option>
                                @foreach($tiposUsuarios as $t)
                                    <option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>
                                @endforeach
                            </select>
                            @error('userEmpresa.tipo_usuario_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="min-w-0 md:col-span-2">
                            <label class="break-words text-sm font-medium text-gray-700">
                                Password {{ $userEditingId ? '(dejar vacío para no cambiar)' : '' }}
                            </label>
                            <input
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                type="password"
                                wire:model.defer="userEmpresa.password"
                            >
                            @error('userEmpresa.password') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        @if($userEditingId)
                            <x-jet-button class="w-full justify-center sm:w-auto" wire:click="updateUser">
                                Guardar cambios
                            </x-jet-button>
                        @else
                            <x-jet-button class="w-full justify-center sm:w-auto" wire:click="storeUser">
                                Crear usuario
                            </x-jet-button>
                        @endif
                    </div>
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="closeUsers">
                Cerrar
            </x-jet-secondary-button>
        </x-slot>
    </x-jet-dialog-modal>

    {{-- Modal eliminar usuario --}}
    <x-jet-dialog-modal wire:model="showUserDeleteModal" maxWidth="md">
        <x-slot name="title">Eliminar usuario</x-slot>

        <x-slot name="content">
            Esta acción es <strong>definitiva</strong>. ¿Deseas eliminar este usuario?
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="cancelDeleteUser">
                Cancelar
            </x-jet-secondary-button>

            <x-jet-danger-button class="ml-2" wire:click="confirmDeleteUser">
                Eliminar
            </x-jet-danger-button>
        </x-slot>
    </x-jet-dialog-modal>

    {{-- Modal servicios --}}
    <x-jet-dialog-modal wire:model="showServicesModal" maxWidth="lg">
        <x-slot name="title">
            <div class="pr-6">
                <div class="font-semibold">Servicios de la empresa</div>
                <div class="break-words text-sm font-normal text-gray-500">{{ $selectedEmpresaNombre }}</div>
            </div>
        </x-slot>

        <x-slot name="content">
            <div class="max-h-[65vh] overflow-y-auto overflow-x-hidden pr-1">
                @if(count($availableServices) > 0)
                    <ul class="divide-y divide-gray-100">
                        @foreach($availableServices as $servicio)
                            <li class="flex items-start gap-3 py-3">
                                <div class="flex h-5 items-center pt-0.5">
                                    <input
                                        type="checkbox"
                                        id="servicio_{{ $servicio['id'] }}"
                                        wire:model="selectedServices"
                                        value="{{ $servicio['id'] }}"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    >
                                </div>
                                <label for="servicio_{{ $servicio['id'] }}" class="min-w-0 cursor-pointer">
                                    <span class="block break-words text-sm font-medium text-gray-900">
                                        {{ $servicio['nombre'] }}
                                    </span>
                                    @if(!empty($servicio['descripcion']))
                                        <span class="mt-0.5 block break-words text-xs text-gray-500">
                                            {{ $servicio['descripcion'] }}
                                        </span>
                                    @endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="py-6 text-center text-sm text-gray-500">
                        No hay servicios disponibles en el catálogo.
                    </p>
                @endif
            </div>
        </x-slot>

        <x-slot name="footer">
            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-jet-secondary-button class="w-full justify-center sm:w-auto" wire:click="closeServicesModal">
                    Cancelar
                </x-jet-secondary-button>

                <x-jet-button class="w-full justify-center sm:w-auto" wire:click="saveServices">
                    Guardar
                </x-jet-button>
            </div>
        </x-slot>
    </x-jet-dialog-modal>
</div>
