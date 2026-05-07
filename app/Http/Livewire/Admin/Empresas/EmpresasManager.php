<?php
namespace App\Http\Livewire\Admin\Empresas;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Pais;
use App\Models\Departamento;
use App\Models\Ciudad;
use App\Models\Empresa;
use App\Models\EmpresaIntegration;
use App\Models\EmpresaWhatsAppSetting;
use App\Models\Servicio;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\IntegrationSecurity\IntegrationCredentialService;
use App\Support\IntegrationSecurity\IntegrationModule;
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

    // Servicios
    public $showServicesModal = false;
    public $selectedEmpresaId = null;
    public $selectedEmpresaNombre = null;
    public $availableServices = [];
    public $selectedServices = [];

    // Integración WordPress UTM
    public $showWpIntegrationModal = false;
    public $wpEmpresaId = null;
    public $wpEmpresaNombre = null;
    public $wpIntegrationId = null;
    public $wpIntegrationPublicKey = null;
    public $wpIntegrationStatus = null;
    public $wpIntegrationLastUsedAt = null;
    public $wpIntegrationScope = null;
    public $wpIntegrationExists = false;
    public $wpPlainSecret = null;

    // Configuración WhatsApp por empresa (reutilizada para WordPress Form Notifications)
    public $wpDestinationPhone = '';
    public $wpDestinationOptIn = false;
    public $wpDestinationOptInAt = null;
    public $wpDestinationOptInSource = '';
    public $wpFormServiceActive = false;
    public $wpSettingsWarning = null;

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
        if (! auth()->check()) {
            abort(401);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isAdmin()) {
            abort(403);
        }

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

public function openServices($empresaId): void
{
    $empresa = Empresa::findOrFail($empresaId);

    $this->selectedEmpresaId     = $empresa->id;
    $this->selectedEmpresaNombre = $empresa->nombre;
    $this->availableServices     = Servicio::where('activo', true)
                                       ->orderBy('nombre')
                                       ->get(['id', 'nombre', 'descripcion'])
                                       ->toArray();
    $this->selectedServices      = $empresa->servicios()
                                       ->pluck('servicios.id')
                                       ->map(fn ($id) => (string) $id)
                                       ->toArray();

    $this->showServicesModal = true;
}

public function saveServices(): void
{
    if (! $this->selectedEmpresaId) return;

    $empresa = Empresa::findOrFail($this->selectedEmpresaId);
    $empresa->servicios()->sync($this->selectedServices);

    session()->flash('message', 'Servicios actualizados correctamente.');
    $this->closeServicesModal();
}

public function closeServicesModal(): void
{
    $this->showServicesModal     = false;
    $this->selectedEmpresaId     = null;
    $this->selectedEmpresaNombre = null;
    $this->availableServices     = [];
    $this->selectedServices      = [];
}

public function openWpIntegration($empresaId): void
{
    $empresa = Empresa::findOrFail($empresaId);

    $this->wpEmpresaId = $empresa->id;
    $this->wpEmpresaNombre = $empresa->nombre;
    $this->wpIntegrationScope = IntegrationModule::SEO_UTM_CONVERSIONS_INGEST;
    $this->showWpIntegrationModal = true;

    $this->refreshWpIntegrationState();
}

public function closeWpIntegration(): void
{
    $this->showWpIntegrationModal = false;
    $this->wpEmpresaId = null;
    $this->wpEmpresaNombre = null;
    $this->wpIntegrationId = null;
    $this->wpIntegrationPublicKey = null;
    $this->wpIntegrationStatus = null;
    $this->wpIntegrationLastUsedAt = null;
    $this->wpIntegrationScope = null;
    $this->wpIntegrationExists = false;
    $this->wpPlainSecret = null;
    $this->wpDestinationPhone = '';
    $this->wpDestinationOptIn = false;
    $this->wpDestinationOptInAt = null;
    $this->wpDestinationOptInSource = '';
    $this->wpFormServiceActive = false;
    $this->wpSettingsWarning = null;
}

public function refreshWpIntegrationState(bool $preserveSecret = false): void
{
    if (! $this->wpEmpresaId) return;

    if (! $preserveSecret) {
        $this->wpPlainSecret = null;
    }

    $integration = $this->resolveWpIntegration($this->wpEmpresaId);

    if (! $integration) {
        $this->wpIntegrationId = null;
        $this->wpIntegrationPublicKey = null;
        $this->wpIntegrationStatus = null;
        $this->wpIntegrationLastUsedAt = null;
        $this->wpIntegrationExists = false;
        $this->wpIntegrationScope = IntegrationModule::SEO_UTM_CONVERSIONS_INGEST;

        $this->loadWpWhatsAppSettings();

        return;
    }

    $service = app(IntegrationCredentialService::class);
    $integration = $service->ensureScope($integration, IntegrationModule::SEO_UTM_CONVERSIONS_INGEST);

    $this->wpIntegrationId = $integration->id;
    $this->wpIntegrationPublicKey = $integration->public_key;
    $this->wpIntegrationStatus = $integration->status;
    $this->wpIntegrationLastUsedAt = optional($integration->last_used_at)->format('Y-m-d H:i:s');
    $this->wpIntegrationExists = true;
    $this->wpIntegrationScope = IntegrationModule::SEO_UTM_CONVERSIONS_INGEST;

    $this->loadWpWhatsAppSettings();
}

public function saveWpWhatsAppSettings(): void
{
    if (! $this->wpEmpresaId) return;

    $rules = [
        'wpDestinationPhone' => ['nullable', 'string', 'max:50'],
        'wpDestinationOptIn' => ['boolean'],
        'wpDestinationOptInAt' => ['nullable', 'date'],
        'wpDestinationOptInSource' => ['nullable', 'string', 'max:80'],
    ];

    $this->validate($rules);

    $phoneRaw = trim((string) $this->wpDestinationPhone);
    $phone = $this->normalizeDestinationPhone($phoneRaw);
    $optIn = (bool) $this->wpDestinationOptIn;
    $optInAt = $this->wpDestinationOptInAt ? (string) $this->wpDestinationOptInAt : null;
    $optInSource = trim((string) $this->wpDestinationOptInSource);

    if ($phoneRaw !== '' && ! preg_match('/^[\d\s\-\(\)\+\.]+$/', $phoneRaw)) {
        $this->addError('wpDestinationPhone', 'destination_phone solo permite dígitos y separadores básicos (espacio, -, (), +, .).');
        return;
    }

    if ($optIn && $phone === '') {
        $this->addError('wpDestinationPhone', 'destination_phone es requerido cuando destination_opt_in está activo.');
        return;
    }

    if ($phone !== '' && substr($phone, 0, 1) === '0') {
        $this->addError('wpDestinationPhone', 'destination_phone no puede iniciar con 0. Usa formato internacional sin ceros iniciales.');
        return;
    }

    if ($phone !== '' && (strlen($phone) < 10 || strlen($phone) > 15)) {
        $this->addError('wpDestinationPhone', 'destination_phone debe contener entre 10 y 15 dígitos.');
        return;
    }

    if ($optIn && $optInAt === null) {
        $this->addError('wpDestinationOptInAt', 'destination_opt_in_at es requerido cuando destination_opt_in está activo.');
        return;
    }

    if ($optIn && $optInSource === '') {
        $this->addError('wpDestinationOptInSource', 'destination_opt_in_source es requerido cuando destination_opt_in está activo.');
        return;
    }

    $setting = EmpresaWhatsAppSetting::query()
        ->where('empresa_id', $this->wpEmpresaId)
        ->first();

    if (! $setting) {
        $setting = new EmpresaWhatsAppSetting();
        $setting->empresa_id = (int) $this->wpEmpresaId;
        $setting->whatsapp_business_phone = 'PENDING';
        $setting->whatsapp_phone_number_id = 'PENDING';
        $setting->whatsapp_access_token = 'PENDING';
        $setting->whatsapp_verify_token = 'PENDING';
        $setting->send_text_enabled = true;
        $setting->send_pdf_enabled = true;
        $setting->save_attachments = false;
        $setting->is_active = true;
    }

    $setting->destination_phone = $phone;
    $setting->destination_opt_in = $optIn;
    $setting->destination_opt_in_at = $optIn ? $optInAt : null;
    $setting->destination_opt_in_source = $optIn ? $optInSource : null;
    $setting->save();

    $this->loadWpWhatsAppSettings();
    session()->flash('message', 'Configuración de destino WhatsApp actualizada.');
}

private function normalizeDestinationPhone(string $phone): string
{
    // Conserva solo dígitos para almacenar un valor compatible con WhatsApp Cloud API.
    return preg_replace('/\D+/', '', $phone);
}

private function loadWpWhatsAppSettings(): void
{
    if (! $this->wpEmpresaId) {
        return;
    }

    $setting = EmpresaWhatsAppSetting::query()
        ->where('empresa_id', $this->wpEmpresaId)
        ->first();

    $this->wpDestinationPhone = $setting ? (string) $setting->destination_phone : '';
    $this->wpDestinationOptIn = $setting ? (bool) $setting->destination_opt_in : false;
    $this->wpDestinationOptInAt = $setting && $setting->destination_opt_in_at
        ? $setting->destination_opt_in_at->format('Y-m-d\TH:i')
        : null;
    $this->wpDestinationOptInSource = $setting ? (string) $setting->destination_opt_in_source : '';

    $empresa = Empresa::query()->find($this->wpEmpresaId);
    $this->wpFormServiceActive = $empresa
        ? $empresa->hasActiveServiceBySlug('formularios-whatsapp-api')
        : false;

    $hasPhone = trim((string) $this->wpDestinationPhone) !== '';
    $hasOptIn = $this->wpDestinationOptIn && ! empty($this->wpDestinationOptInAt);

    $this->wpSettingsWarning = null;
    if ($this->wpFormServiceActive && (! $hasPhone || ! $hasOptIn)) {
        $this->wpSettingsWarning = 'El servicio formularios-whatsapp-api está activo, pero falta destination_phone u opt-in confirmado.';
    }
}

public function createWpIntegration(): void
{
    if (! $this->wpEmpresaId) return;

    $existing = $this->resolveWpIntegration($this->wpEmpresaId);
    if ($existing) {
        $this->refreshWpIntegrationState();
        session()->flash('message', 'La integración WordPress UTM ya existe para esta empresa.');
        return;
    }

    $empresa = Empresa::findOrFail($this->wpEmpresaId);
    $service = app(IntegrationCredentialService::class);
    $issued = $service->createWordpressUtm($empresa);

    $this->wpPlainSecret = $issued->plainSecret;
    $this->refreshWpIntegrationState(true);

    session()->flash('message', 'Integración WordPress UTM creada correctamente. Copia el secreto ahora, no se mostrará nuevamente.');
}

public function activateWpIntegration(): void
{
    if (! $this->wpEmpresaId) return;

    $integration = $this->resolveWpIntegration($this->wpEmpresaId);
    if (! $integration) return;

    $service = app(IntegrationCredentialService::class);
    $service->activate($integration);

    $this->refreshWpIntegrationState();
    session()->flash('message', 'Integración activada.');
}

public function suspendWpIntegration(): void
{
    if (! $this->wpEmpresaId) return;

    $integration = $this->resolveWpIntegration($this->wpEmpresaId);
    if (! $integration) return;

    $service = app(IntegrationCredentialService::class);
    $service->deactivate($integration);

    $this->refreshWpIntegrationState();
    session()->flash('message', 'Integración suspendida.');
}

public function revokeWpIntegration(): void
{
    if (! $this->wpEmpresaId) return;

    $integration = $this->resolveWpIntegration($this->wpEmpresaId);
    if (! $integration) return;

    $service = app(IntegrationCredentialService::class);
    $service->revoke($integration);

    $this->refreshWpIntegrationState();
    session()->flash('message', 'Integración revocada.');
}

public function rotateWpSecret(): void
{
    if (! $this->wpEmpresaId) return;

    $integration = $this->resolveWpIntegration($this->wpEmpresaId);
    if (! $integration) return;

    $service = app(IntegrationCredentialService::class);
    $issued = $service->rotateSecret($integration);

    $this->wpPlainSecret = $issued->plainSecret;
    $this->refreshWpIntegrationState(true);

    session()->flash('message', 'Secreto regenerado. Copia el valor ahora, no se mostrará nuevamente.');
}

private function resolveWpIntegration(int $empresaId): ?EmpresaIntegration
{
    $integrations = EmpresaIntegration::query()
        ->where('empresa_id', $empresaId)
        ->orderByDesc('id')
        ->get();

    return $integrations->first(function (EmpresaIntegration $integration) {
        if ($integration->provider_type === 'wordpress') {
            return true;
        }

        $scopes = $integration->scopes_json ?? [];
        if (! is_array($scopes)) {
            $scopes = [];
        }

        return in_array(IntegrationModule::SEO_UTM_CONVERSIONS_INGEST, $scopes, true);
    });
}

public function destroy($id)
{
    $empresa = Empresa::findOrFail($id);

    if ($empresa->proyectos()->exists()) {
        session()->flash('message', 'No se puede eliminar la empresa porque tiene servicios/proyectos asociados.');
        return;
    }

    $empresa->delete();

    if ((int) $this->editingId === (int) $empresa->id) {
        $this->showModal = false;
        $this->resetForm();
    }

    session()->flash('message', 'Empresa eliminada correctamente.');
}


}
