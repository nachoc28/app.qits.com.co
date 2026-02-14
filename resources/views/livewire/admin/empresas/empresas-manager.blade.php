<div class="py-6">
    {{-- Flash message --}}
    @if (session()->has('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 ">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white shadow-sm ring-1 ring-black/5 -xl p-6 space-y-6">
        {{-- Barra de herramientas --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            {{-- izquierda: buscador + perPage --}}
            {{-- Toolbar compacta en una fila --}}
            <div class="flex items-center space-x-4 gap-6 flex-nowrap ">
                <input
                    type="text"
                    wire:model.debounce.400ms="search"
                    placeholder="Buscar por nombre, NIT o email"
                    class="w-90 flex-1 min-w-0 border px-3 py-2"
                />

                <select
                    wire:model="perPage"
                    class="w-20 shrink-0 border px-2 py-2"
                    title="Items por página"
                >
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>

                <x-jet-button
                    class="ml-auto shrink-0 whitespace-nowrap"
                    wire:click="new"
                >
                    + Nueva empresa
                </x-jet-button>
            </div>
        </div>

        {{-- Tabla full width --}}
        <div class="overflow-x-auto">
            <table class="min-w-full w-full table-auto text-sm">
                <thead class="bg-gray-50">
                <tr class="text-left">
                    <th class="px-3 py-2">Logo</th>
                    <th class="px-3 py-2">Nombre</th>
                    <th class="px-3 py-2">NIT</th>
                    <th class="px-3 py-2">Ciudad</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">Teléfono</th>
                    <th class="px-3 py-2 w-32"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($empresas as $e)
                    <tr class="border-b">
                        <td class="px-3 py-2">
                            @if($e->logo)
                                <img src="{{ asset('storage/'.$e->logo) }}" alt="logo"
                                     class="w-10 h-10  object-cover">
                            @else
                                <div class="w-10 h-10 bg-gray-200 "></div>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-medium">{{ $e->nombre }}</td>
                        <td class="px-3 py-2">{{ $e->nit }}</td>
                        <td class="px-3 py-2">
                            {{ $e->ciudad->nombre ?? '-' }},
                            {{ optional($e->ciudad->departamento)->nombre ?? '-' }}
                        </td>
                        <td class="px-3 py-2">{{ $e->email }}</td>
                        <td class="px-3 py-2">{{ $e->telefono }}</td>
                        <td class="px-3 py-2 text-right">
                            <div class="inline-flex justify-end w-full">
                                <x-jet-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <button type="button"
                                                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium
                                                       border shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2
                                                       focus:ring-offset-2 focus:ring-indigo-500">
                                            Acciones
                                            <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        {{-- Ver / detalles (si tienes modal show) --}}
                                        <x-jet-dropdown-link href="#" wire:click.prevent="show({{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Ver
                                            </div>
                                        </x-jet-dropdown-link>

                                        {{-- Editar --}}
                                        <x-jet-dropdown-link href="#" wire:click.prevent="edit({{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15.232 5.232l3.536 3.536M4 20h4.586a1 1 0 00.707-.293l9.414-9.414a2 2 0 10-2.828-2.828L6.465 16.879A1 1 0 006.172 17.586V20z"/>
                                                </svg>
                                                Editar
                                            </div>
                                        </x-jet-dropdown-link>

                                        {{-- Activar / Inactivar --}}
                                        <button type="button"
                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 focus:outline-none"
                                                wire:click="openToggle({{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M5 13l4 4L19 7"/>
                                                </svg>
                                            {{ $e->active ? 'Inactivar' : 'Activar' }}
                                            </div>
                                        </button>

                                        {{-- Usuarios --}}
                                        <x-jet-dropdown-link href="#" wire:click.prevent="openUsers({{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M15 11a4 4 0 10-6 0 4 4 0 006 0z"/>
                                                </svg>
                                                Usuarios
                                            </div>
                                        </x-jet-dropdown-link>

                                        {{-- Servicios (placeholder) --}}
                                        <x-jet-dropdown-link href="#" wire:click.prevent="openServices({{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M12 8v8m4-4H8"/>
                                                </svg>
                                                Servicios
                                            </div>
                                        </x-jet-dropdown-link>

                                        <div class="border-t my-2"></div>

                                        {{-- Eliminar (soft delete) --}}
                                        <button type="button"
                                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 focus:outline-none"
                                                onclick="if(confirm('¿Eliminar empresa?')) @this.call('destroy', {{ $e->id }})">
                                            <div class="flex items-center">
                                                <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M4 7h16"/>
                                                </svg>
                                                Eliminar
                                            </div>
                                        </button>
                                    </x-slot>
                                </x-jet-dropdown>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                      <td colspan="7" class="px-3 py-10">
                        <div class="text-center text-gray-500">
                          <div class="text-sm">Sin resultados</div>
                          <div class="mt-3">
                            <x-jet-secondary-button wire:click="new">Crear la primera empresa</x-jet-secondary-button>
                          </div>
                        </div>
                      </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            <div class="flex justify-end">
                {{ $empresas->links() }}
            </div>
        </div>

    </div>

    {{-- Modal crear/editar --}}
    <x-jet-dialog-modal wire:model="showModal" maxWidth="2xl">
        <x-slot name="title">
            {{ $isViewing ? 'Detalle de empresa'
                  : ($editingId ? 'Editar empresa' : 'Nueva empresa') }}
        </x-slot>

        <x-slot name="content">
            <fieldset @if($isViewing) disabled @endif>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Columna principal --}}
                    <div class="md:col-span-2 space-y-6">
                        <div>
                            <x-jet-label value="Nombre" />
                            <x-jet-input type="text" class="w-full" wire:model.defer="empresa.nombre"/>
                            <x-jet-input-error for="empresa.nombre"/>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <x-jet-label value="NIT" />
                                <x-jet-input type="text" class="w-full" wire:model.defer="empresa.nit"/>
                                <x-jet-input-error for="empresa.nit"/>
                            </div>
                            <div>
                                <x-jet-label value="Email" />
                                <x-jet-input type="email" class="w-full" wire:model.defer="empresa.email"/>
                                <x-jet-input-error for="empresa.email"/>
                            </div>
                        </div>

                        <div>
                            <x-jet-label value="Dirección" />
                            <x-jet-input type="text" class="w-full" wire:model.defer="empresa.direccion"/>
                            <x-jet-input-error for="empresa.direccion"/>
                        </div>

                        {{-- Selects dependientes País / Dpto / Ciudad --}}
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <div>
                                <x-jet-label value="País" />
                                <select class="w-full border  px-3 py-2"
                                        wire:model="pais_id" >
                                    <option value="">Seleccione...</option>
                                    @foreach($paises as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-jet-label value="Departamento/Estado" />
                                <select class="w-full border  px-3 py-2"
                                        wire:model="departamento_id" >
                                    <option value="">Seleccione...</option>
                                    @foreach($departamentos as $d)
                                        <option value="{{ $d['id'] }}">{{ $d['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-jet-label value="Ciudad/Municipio" />
                                <select class="w-full border  px-3 py-2"
                                        wire:model.defer="empresa.ciudad_id">
                                    <option value="">Seleccione...</option>
                                    @foreach($ciudades as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>
                                    @endforeach
                                </select>
                                <x-jet-input-error for="empresa.ciudad_id"/>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <x-jet-label value="Teléfono" />
                                <x-jet-input type="text" class="w-full" wire:model.defer="empresa.telefono"/>
                                <x-jet-input-error for="empresa.telefono"/>
                            </div>
                        </div>
                    </div>

                    {{-- Columna lateral: Logo + Usuario Cliente --}}
                    <div class="space-y-6">
                        <div>
                            <x-jet-label value="Logo" />
                            @if(!$isViewing)
                            <input type="file" wire:model="logoFile" class="w-full">
                            <x-jet-input-error for="logoFile"/>
                            @endif
                            <div class="mt-2">
                                @if ($logoFile)
                                    {{-- preview temporal --}}
                                    <img src="{{ $logoFile->temporaryUrl() }}" class="h-24  shadow">
                                @elseif($empresa['logo'])
                                    {{-- logo guardado --}}
                                    <img src="{{ asset('storage/'.$empresa['logo']) }}" class="h-24  shadow">
                                @endif
                            </div>
                            {{-- progreso de carga --}}
                            <div wire:loading wire:target="logoFile" class="text-xs text-gray-500 mt-1">
                                Subiendo archivo...
                            </div>
                        </div>
                        @if(!$isViewing)
                        <div class="border-t pt-3">
                            <label class="inline-flex items-center">
                                <input type="checkbox" wire:model="createUser" class="mr-2">
                                <span>Crear usuario Cliente</span>
                            </label>

                            @if($createUser)
                                <div class="mt-3 space-y-6">
                                    <div>
                                        <x-jet-label value="Nombre del usuario" />
                                        <x-jet-input type="text" class="w-full" wire:model.defer="user_name"/>
                                        <x-jet-input-error for="user_name"/>
                                    </div>
                                    <div>
                                        <x-jet-label value="Email del usuario" />
                                        <x-jet-input type="email" class="w-full" wire:model.defer="user_email"/>
                                        <x-jet-input-error for="user_email"/>
                                    </div>
                                    <div>
                                        <x-jet-label value="Teléfono del usuario" />
                                        <x-jet-input type="text" class="w-full" wire:model.defer="user_phone"/>
                                    </div>
                                    <div>
                                        <x-jet-label value="Contraseña" />
                                        <x-jet-input type="text" class="w-full" wire:model.defer="user_password"
                                                     placeholder="Mínimo 8 caracteres"/>
                                        <x-jet-input-error for="user_password"/>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
            </fieldset>
        </x-slot>

        <x-slot name="footer">
            @if($isViewing)
                <x-jet-secondary-button wire:click="$set('showModal', false)">Cerrar</x-jet-secondary-button>
            @else
                <x-jet-secondary-button wire:click="$set('showModal', false)">Cancelar</x-jet-secondary-button>
                <x-jet-button class="ml-2" wire:click="{{ $editingId ? 'update' : 'store' }}">
                    Guardar
                </x-jet-button>
            @endif
        </x-slot>
    </x-jet-dialog-modal>

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
                <ul class="list-disc ml-5 text-sm text-gray-600">
                    <li>Sus usuarios no podrán acceder al sistema.</li>
                    <li>Las integraciones (UTM, WhatsApp, Mailrelay) deberán dejar de sincronizar.</li>
                </ul>
            @endif
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-jet-secondary-button wire:click="$set('showToggleModal', false)">Cancelar</x-jet-secondary-button>
        <x-jet-button class="ml-2"
                      wire:click="confirmToggle">
            {{ $toggleTargetActive ? 'Activar' : 'Inactivar' }}
        </x-jet-button>
    </x-slot>
</x-jet-dialog-modal>

<x-jet-dialog-modal wire:model="showUsersModal" maxWidth="2xl">
    <x-slot name="title">
        <div class="pr-6">
            <div class="font-semibold">Usuarios</div>
            <div class="text-sm text-gray-500 break-words">{{ $usersEmpresaNombre }}</div>
        </div>
    </x-slot>

    <x-slot name="content">
        <div class="max-h-[75vh] overflow-y-auto pr-1 space-y-4">

           <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <input type="text"
                       wire:model.debounce.400ms="usersSearch"
                       class="w-full sm:max-w-md border px-3 py-2 rounded"
                       placeholder="Buscar por nombre, email o teléfono" />

                <x-jet-secondary-button class="w-full sm:w-auto justify-center" wire:click="newUser">
                    + Nuevo usuario
                </x-jet-secondary-button>
            </div>

            {{-- Tabla usuarios --}}
           <div class="hidden sm:block overflow-x-auto border rounded">
                <table class="min-w-[900px] w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left">
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Teléfono</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 w-40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            <tr class="border-t">
                                <td class="px-3 py-2">{{ $u['name'] }}</td>
                                <td class="px-3 py-2">{{ $u['email'] }}</td>
                                <td class="px-3 py-2">{{ $u['telefono'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $u['tipo_usuario']['nombre'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-1 rounded text-xs {{ ($u['active'] ?? true) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ ($u['active'] ?? true) ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right space-x-2">
                                    <x-jet-secondary-button wire:click="editUser({{ $u['id'] }})">
                                        Editar
                                    </x-jet-secondary-button>

                                    <button type="button"
                                            class="px-3 py-2 border rounded"
                                            wire:click="openToggleUser({{ $u['id'] }})">
                                        {{ ($u['active'] ?? true) ? 'Inactivar' : 'Activar' }}
                                    </button>
                                    <button type="button"
                                            class="px-3 py-2 border rounded text-red-600"
                                            wire:click="openDeleteUser({{ $u['id'] }})">
                                        Eliminar
                                    </button>

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
                <div class="sm:hidden space-y-3">
                    @forelse($users as $u)
                        <div class="border rounded p-3 space-y-2">
                            <div class="font-semibold">{{ $u['name'] }}</div>
                            <div class="text-sm text-gray-600 break-words">{{ $u['email'] }}</div>

                            <div class="text-sm">
                                <span class="font-medium">Tel:</span> {{ $u['telefono'] ?? '-' }}
                            </div>

                            <div class="text-sm">
                                <span class="font-medium">Tipo:</span> {{ $u['tipo_usuario']['nombre'] ?? '-' }}
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs px-2 py-1 rounded {{ ($u['active'] ?? true) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ ($u['active'] ?? true) ? 'Activo' : 'Inactivo' }}
                                </span>

                                <div class="flex gap-2">
                                    <button class="px-3 py-2 border rounded" wire:click="editUser({{ $u['id'] }})">Editar</button>
                                    <button class="px-3 py-2 border rounded" wire:click="openToggleUser({{ $u['id'] }})">
                                        {{ ($u['active'] ?? true) ? 'Inactivar' : 'Activar' }}
                                    </button>
                                    <button class="px-3 py-2 border rounded text-red-600" wire:click="openDeleteUser({{ $u['id'] }})">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-6">Sin usuarios</div>
                    @endforelse
                </div>

            </div>

            {{-- Formulario crear/editar --}}
            <div class="border rounded p-4 space-y-3">
                <div class="font-semibold">
                    {{ $userEditingId ? 'Editar usuario' : 'Crear usuario' }}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm">Nombre</label>
                        <input class="w-full border px-3 py-2" type="text" wire:model.defer="userEmpresa.name">
                        @error('userEmpresa.name') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="text-sm">Email</label>
                        <input class="w-full border px-3 py-2" type="email" wire:model.defer="userEmpresa.email">
                        @error('userEmpresa.email') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="text-sm">Teléfono</label>
                        <input class="w-full border px-3 py-2" type="text" wire:model.defer="userEmpresa.telefono">
                        @error('userEmpresa.telefono') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="text-sm">Tipo usuario</label>
                        <select class="w-full border px-3 py-2" wire:model.defer="userEmpresa.tipo_usuario_id">
                            <option value="">-- Seleccionar --</option>
                            @foreach($tiposUsuarios as $t)
                                <option value="{{ $t['id'] }}">{{ $t['nombre'] }}</option>
                            @endforeach
                        </select>
                        @error('userEmpresa.tipo_usuario_id') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="text-sm">
                            Password {{ $userEditingId ? '(dejar vacío para no cambiar)' : '' }}
                        </label>
                        <input class="w-full border px-3 py-2" type="password" wire:model.defer="userEmpresa.password">
                        @error('userEmpresa.password') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
                    @if($userEditingId)
                        <x-jet-button class="w-full sm:w-auto justify-center" wire:click="updateUser">Guardar cambios</x-jet-button>
                    @else
                        <x-jet-button class="w-full sm:w-auto justify-center" wire:click="storeUser">Crear usuario</x-jet-button>
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


</div>
