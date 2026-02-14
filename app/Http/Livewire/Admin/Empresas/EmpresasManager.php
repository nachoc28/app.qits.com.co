<?php
namespace App\Http\Livewire\Admin\Empresas;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Pais;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmpresasManager extends Component
{
    use WithPagination, WithFileUploads;

    public $showModal = false;
    public $isViewing = false;
    public $editingId = null;
    public $search = '';
    public $perPage = 10;
    public $showToggleModal = false;
    public $toggleId = null;
    public $toggleTargetActive = null;

    public $empresa = [
        'nit' => '',
        'nombre' => '',
        'direccion' => '',
        'ciudad_id' => null,
        'telefono' => '',
        'email' => '',
        'logo' => null,
    ];

    protected $paginationTheme = 'tailwind';

    public $logoFile;

    public $pais_id = null;
    public $departamento_id = null;
    public $paises = [];
    public $departamentos = [];
    public $ciudades = [];

    public $createUser = false;
    public $user_name = '';
    public $user_email = '';
    public $user_phone = '';
    public $user_password = '';

    public $showUsersModal = false;
    public $usersEmpresaId = null;
    public $usersEmpresaNombre = null;

    public $usersSearch = '';
    public $users = []; // lista simple (array) para pintar en el modal

    public $userEditingId = null;

    public $userEmpresa = [
        'name' => '',
        'email' => '',
        'telefono' => '',
        'password' => '',
        'tipo_usuario_id' => null,
        'active' => true,
    ];

    public $tiposUsuarios = [];

    // Toggle usuario
    public $showUserToggleModal = false;
    public $userToggleId = null;
    public $userToggleTargetActive = null;

    public $showUserDeleteModal = false;
    public $userDeleteId = null;

    protected function baseRules(): array
    {
        return [
            'empresa.nit'       => ['required','string','max:50'],
            'empresa.nombre'    => ['required','string','max:180'],
            'empresa.direccion' => ['nullable','string','max:180'],
            'empresa.ciudad_id' => ['required','exists:ciudades,id'],
            'empresa.telefono'  => ['nullable','string','max:50'],
            'empresa.email'     => ['nullable','email','max:180'],
            'logoFile'          => ['nullable','image','max:2048'],
            'createUser'        => ['boolean'],
            'user_name'         => ['nullable','required_if:createUser,true','string','max:150'],
            'user_email'        => ['nullable','required_if:createUser,true','email','max:180'],
            'user_phone'        => ['nullable','string','max:50'],
            'user_password'     => ['nullable','required_if:createUser,true','string','min:8'],
        ];
    }

    public function mount()
    {
        $this->paises = Pais::orderBy('nombre')->get(['id','nombre'])->toArray();
        $this->pais_id = Pais::where('iso2','CO')->value('id');
        $this->loadSelects();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function new()
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
        $this->isViewing = false;
    }

    public function edit($id)
    {
        $this->resetForm();
        $this->isViewing = false;

        $this->editingId = (int) $id;
        $e = Empresa::with('ciudad.departamento.pais')->findOrFail($id);

        $this->empresa = [
            'nit'       => $e->nit,
            'nombre'    => $e->nombre,
            'direccion' => $e->direccion,
            'ciudad_id' => $e->ciudad_id,
            'telefono'  => $e->telefono,
            'email'     => $e->email,
            'logo'      => $e->logo,
        ];

        $this->pais_id = $e->ciudad->departamento->pais->id ?? null;
        $this->departamento_id = $e->ciudad->departamento->id ?? null;

        $this->loadSelects();
        $this->showModal = true;
    }

    public function updatedPaisId($value)
    {
        $this->departamento_id = null;
        $this->empresa['ciudad_id'] = null;
        $this->loadSelects(); // recarga departamentos y ciudades según $this->pais_id
    }

    public function updatedDepartamentoId($value)
    {
        $this->empresa['ciudad_id'] = null;
        $this->loadCities(); // recarga ciudades según $this->departamento_id
    }

    private function loadSelects(): void
    {
        // países (por si se actualizó catálogo)
        $this->paises = Pais::orderBy('nombre')->get(['id','nombre'])->toArray();

        // departamentos del país seleccionado
        $this->departamentos = $this->pais_id
            ? Departamento::where('pais_id', $this->pais_id)
                ->orderByRaw("CASE WHEN nombre='Indefinido' THEN 0 ELSE 1 END, nombre")
                ->get(['id','nombre'])->toArray()
            : [];

        // ciudades del depto seleccionado
        $this->loadCities();
    }

    private function loadCities(): void
    {
        $this->ciudades = $this->departamento_id
            ? Ciudad::where('departamento_id', $this->departamento_id)
                ->orderByRaw("CASE WHEN nombre='Indefinido' THEN 0 ELSE 1 END, nombre")
                ->get(['id','nombre'])->toArray()
            : [];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId','logoFile',
            'pais_id','departamento_id','departamentos','ciudades',
            'createUser','user_name','user_email','user_phone','user_password'
        ]);

        $this->empresa = [
            'nit' => '',
            'nombre' => '',
            'direccion' => '',
            'ciudad_id' => null,
            'telefono' => '',
            'email' => '',
            'logo' => null,
        ];

        $this->paises = Pais::orderBy('nombre')->get(['id','nombre'])->toArray();
    }

    public function render()
    {
        $empresas = Empresa::with('ciudad.departamento.pais')
            ->when($this->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('nombre','like',"%{$this->search}%")
                      ->orWhere('nit','like',"%{$this->search}%")
                      ->orWhere('email','like',"%{$this->search}%")
                )
            )
            ->orderBy('nombre')
            ->paginate($this->perPage);

        return view('livewire.admin.empresas.empresas-manager', [
            'empresas' => $empresas,
        ]);
    }

    protected function handleLogoUpload(?Empresa $empresa = null): void
    {
        if ($this->logoFile) {
            $path = $this->logoFile->store('logos', 'public');

            // si es update y había logo previo, elimínalo
            if ($empresa && $empresa->logo) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $this->empresa['logo'] = $path;
        }
    }

    protected function maybeCreateCliente(Empresa $empresa): void
    {
        if (! $this->createUser) return;

        $tipoClienteId = TipoUsuario::where('nombre','Cliente')->value('id');
        if (! $tipoClienteId) return;

        $user = User::firstOrCreate(
            ['email' => $this->user_email],
            [
                'name'            => $this->user_name,
                'password'        => Hash::make($this->user_password ?: Str::random(12)),
                'telefono'        => $this->user_phone,
                'empresa_id'      => $empresa->id,
                'tipo_usuario_id' => $tipoClienteId,
                'email_verified_at' => now(),
            ]
        );

        if ($user->empresa_id !== $empresa->id || $user->tipo_usuario_id !== $tipoClienteId) {
            $user->empresa_id = $empresa->id;
            $user->tipo_usuario_id = $tipoClienteId;
            $user->save();
        }
    }

    public function store(): void
    {
        $rules = $this->baseRules();
        $rules['empresa.nit'][]   = Rule::unique('empresas','nit');
        $rules['empresa.email'][] = Rule::unique('empresas','email');

        $this->validate($rules);

        $this->handleLogoUpload(null);

        $empresa = Empresa::create($this->empresa);

        $this->maybeCreateCliente($empresa);

        session()->flash('message', 'Empresa creada correctamente.');
        $this->showModal = false;
        $this->resetForm();
    }

    public function update(): void
    {
        if (!$this->editingId) return;

        $empresa = Empresa::findOrFail($this->editingId);

        $rules = $this->baseRules();
        $rules['empresa.nit'][]   = Rule::unique('empresas','nit')->ignore($empresa->id);
        $rules['empresa.email'][] = Rule::unique('empresas','email')->ignore($empresa->id);

        $this->validate($rules);

        $this->handleLogoUpload($empresa);

        $empresa->update($this->empresa);

        $this->maybeCreateCliente($empresa);

        session()->flash('message', 'Empresa actualizada correctamente.');
        $this->showModal = false;
        $this->resetForm();
    }

    public function show($id): void
    {
        $this->resetForm();
        $this->isViewing = true;       // ← solo lectura
        $this->editingId = $id;        // reutilizamos las mismas cargas de edición

        $e = Empresa::with('ciudad.departamento.pais')->findOrFail($id);

        $this->empresa = [
            'nit'       => $e->nit,
            'nombre'    => $e->nombre,
            'direccion' => $e->direccion,
            'ciudad_id' => $e->ciudad_id,
            'telefono'  => $e->telefono,
            'email'     => $e->email,
            'logo'      => $e->logo,
        ];

        // selects dependientes
        $this->pais_id = optional(optional($e->ciudad)->departamento)->pais_id;
        $this->departamento_id = optional($e->ciudad)->departamento_id;
        $this->loadSelects();

        $this->showModal = true;
    }

    public function openToggle($id)
    {
        $empresa = Empresa::findOrFail($id);
        $this->toggleId = $empresa->id;
        $this->toggleTargetActive = ! $empresa->active;
        $this->showToggleModal = true;
    }

    public function confirmToggle()
    {
        if (!$this->toggleId) return;

        $empresa = Empresa::findOrFail($this->toggleId);

        $empresa->active = (bool) $this->toggleTargetActive;
        $empresa->save();

        // Opcional: al inactivar, desactivar usuarios de la empresa (si ya creaste users.active)
        // \App\Models\User::where('empresa_id', $empresa->id)->update(['active' => $empresa->active]);

        $this->showToggleModal = false;
        $this->toggleId = null;
        $this->toggleTargetActive = null;

        session()->flash('message', $empresa->active ? 'Empresa activada.' : 'Empresa inactivada.');
    }

    public function openUsers($empresaId)
    {
        $empresa = Empresa::findOrFail($empresaId);

        $this->usersEmpresaId = $empresa->id;
        $this->usersEmpresaNombre = $empresa->nombre;

        $this->tiposUsuarios = TipoUsuario::orderBy('nombre')->get(['id','nombre'])->toArray();

        $this->resetUserEmpresaForm();
        $this->loadUsers();

        $this->showUsersModal = true;
    }

    public function closeUsers()
    {
        $this->showUsersModal = false;
        $this->usersEmpresaId = null;
        $this->usersEmpresaNombre = null;
        $this->usersSearch = '';
        $this->users = [];
        $this->userEditingId = null;
        $this->resetUserEmpresaForm();
    }

    public function updatedUsersSearch()
    {
        $this->loadUsers();
    }

    private function loadUsers(): void
    {
        if (!$this->usersEmpresaId) {
            $this->users = [];
            return;
        }

        $query = User::query()
            ->with('tipoUsuario')
            ->where('empresa_id', $this->usersEmpresaId)
            ->when($this->usersSearch, function ($q) {
                $term = $this->usersSearch;
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('telefono', 'like', "%{$term}%");
                });
            })
            ->orderBy('name');

        $this->users = $query->get()->toArray();
    }

    private function resetUserEmpresaForm(): void
    {
        $this->userEditingId = null;

        $this->userEmpresa = [
            'name' => '',
            'email' => '',
            'telefono' => '',
            'password' => '',
            'tipo_usuario_id' => null,
            'active' => true,
        ];
    }

    private function userRules(): array
{
    return [
        'userEmpresa.name' => ['required','string','max:150'],
        'userEmpresa.email' => ['required','email','max:180'],
        'userEmpresa.telefono' => ['nullable','string','max:50'],
        'userEmpresa.tipo_usuario_id' => ['required','exists:tipos_usuarios,id'],
        'userEmpresa.password' => ['nullable','string','min:8'],
    ];
}

public function newUser()
{
    $this->resetUserEmpresaForm();
}

public function editUser($userId)
{
    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($userId);

    $this->userEditingId = $u->id;

    $this->userEmpresa = [
        'name' => $u->name,
        'email' => $u->email,
        'telefono' => $u->telefono,
        'password' => '', // nunca traemos password
        'tipo_usuario_id' => $u->tipo_usuario_id,
        'active' => (bool) $u->active,
    ];
}

public function storeUser()
{
    if (!$this->usersEmpresaId) return;

    $rules = $this->userRules();
    $rules['userEmpresa.email'][] = Rule::unique('users','email');

    // password requerido en creación
    $rules['userEmpresa.password'] = ['required','string','min:8'];

    $this->validate($rules);

    User::create([
        'name' => $this->userEmpresa['name'],
        'email' => $this->userEmpresa['email'],
        'telefono' => $this->userEmpresa['telefono'],
        'empresa_id' => $this->usersEmpresaId,
        'tipo_usuario_id' => $this->userEmpresa['tipo_usuario_id'],
        'active' => true,
        'password' => Hash::make($this->userEmpresa['password']),
        'email_verified_at' => now(),
    ]);

    session()->flash('message', 'Usuario creado correctamente.');
    $this->resetUserEmpresaForm();
    $this->loadUsers();
}

public function updateUser()
{
    if (!$this->usersEmpresaId || !$this->userEditingId) return;

    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($this->userEditingId);

    $rules = $this->userRules();
    $rules['userEmpresa.email'][] = Rule::unique('users','email')->ignore($u->id);

    $this->validate($rules);

    $u->name = $this->userEmpresa['name'];
    $u->email = $this->userEmpresa['email'];
    $u->telefono = $this->userEmpresa['telefono'];
    $u->tipo_usuario_id = $this->userEmpresa['tipo_usuario_id'];

    if (!empty($this->userEmpresa['password'])) {
        $u->password = Hash::make($this->userEmpresa['password']);
    }

    $u->save();

    session()->flash('message', 'Usuario actualizado correctamente.');
    $this->resetUserEmpresaForm();
    $this->loadUsers();
}

public function openToggleUser($userId)
{
    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($userId);

    $this->userToggleId = $u->id;
    $this->userToggleTargetActive = !$u->active;
    $this->showUserToggleModal = true;
}

public function confirmToggleUser()
{
    if (!$this->usersEmpresaId || !$this->userToggleId) return;

    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($this->userToggleId);
    $u->active = (bool) $this->userToggleTargetActive;
    $u->save();

    $this->showUserToggleModal = false;
    $this->userToggleId = null;
    $this->userToggleTargetActive = null;

    session()->flash('message', $u->active ? 'Usuario activado.' : 'Usuario inactivado.');
    $this->loadUsers();
}

public function cancelToggleUser()
{
    $this->showUserToggleModal = false;
    $this->userToggleId = null;
    $this->userToggleTargetActive = null;
}

public function openDeleteUser($userId)
{
    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($userId);

    // Recomendado: evitar borrarte a ti mismo
    if (auth()->id() === $u->id) {
        session()->flash('message', 'No puedes eliminar tu propio usuario.');
        return;
    }

    $this->userDeleteId = $u->id;
    $this->showUserDeleteModal = true;
}

public function confirmDeleteUser()
{
    if (!$this->usersEmpresaId || !$this->userDeleteId) return;

    $u = User::where('empresa_id', $this->usersEmpresaId)->findOrFail($this->userDeleteId);

    // Si tienes FK/relaciones que impiden borrar, aquí podrías fallback a inactivar:
    // $u->active = false; $u->save();
    // Por ahora: eliminación definitiva
    $u->delete();

    $this->showUserDeleteModal = false;
    $this->userDeleteId = null;

    session()->flash('message', 'Usuario eliminado correctamente.');
    $this->loadUsers();
}

public function cancelDeleteUser()
{
    $this->showUserDeleteModal = false;
    $this->userDeleteId = null;
}


}
