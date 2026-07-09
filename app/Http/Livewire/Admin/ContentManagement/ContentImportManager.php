<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\Empresa;
use App\Models\User;
use App\Services\ContentManagement\ContentXlsxImportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ContentImportManager extends Component
{
    use WithFileUploads;

    /** @var array<int, array{id:int,nombre:string}> */
    public $authorizedEmpresas = [];

    /** @var int|string|null */
    public $selectedEmpresaId;

    /** @var string|null */
    public $tone;

    /** @var mixed */
    public $xlsxFile;

    /** @var array<string, mixed>|null */
    public $previewResult = null;

    /** @var string|null */
    public $importError = null;

    /** @var string|null */
    public $stagedFilePath = null;

    /** @var bool */
    public $canConfirmImport = false;

    public function mount(): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        /** @var User $user */
        $user = auth()->user();
        $empresas = $this->resolveAuthorizedEmpresas($user);

        if ($empresas->isEmpty()) {
            abort(403);
        }

        $this->authorizedEmpresas = $empresas
            ->map(function (Empresa $empresa): array {
                return [
                    'id' => (int) $empresa->id,
                    'nombre' => (string) $empresa->nombre,
                ];
            })
            ->values()
            ->all();

        $this->selectedEmpresaId = $this->authorizedEmpresas[0]['id'] ?? null;
        $this->tone = null;
    }

    public function updatedSelectedEmpresaId(): void
    {
        $this->resetImportState(app(ContentXlsxImportService::class), true);
    }

    public function updatedTone(): void
    {
        $this->resetImportState(app(ContentXlsxImportService::class), true);
    }

    public function updatedXlsxFile(): void
    {
        $this->resetImportState(app(ContentXlsxImportService::class), true);
        $this->validateOnly('xlsxFile', $this->fileRules(), $this->messages());
    }

    public function validateImport(): void
    {
        /** @var ContentXlsxImportService $service */
        $service = app(ContentXlsxImportService::class);
        $this->resetImportState($service, true);
        $this->validate($this->rules(), $this->messages());

        $empresa = $this->resolveSelectedEmpresa();

        try {
            $preview = $service->previewUploadedFile($empresa, $this->xlsxFile, [
                'tone' => $this->tone,
            ]);

            $this->stagedFilePath = isset($preview['stored_path']) && is_string($preview['stored_path'])
                ? $preview['stored_path']
                : null;
            $this->previewResult = $this->prepareResultForView($preview);
            $this->canConfirmImport = (bool) ($preview['can_persist'] ?? false) && $this->stagedFilePath !== null;
        } catch (Throwable $e) {
            Log::error('[CONTENT][XLSX][PREVIEW] Error durante validación previa.', [
                'empresa_id' => $empresa->id,
                'user_id' => auth()->id(),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            $service->deleteTemporaryFile($this->stagedFilePath);
            $this->stagedFilePath = null;
            $this->canConfirmImport = false;
            $this->importError = 'Error interno al validar el archivo XLSX.';
        }
    }

    public function confirmImport(): void
    {
        /** @var ContentXlsxImportService $service */
        $service = app(ContentXlsxImportService::class);

        if (! $this->canConfirmImport || ! is_string($this->stagedFilePath) || $this->stagedFilePath === '') {
            $this->importError = 'Debes validar un archivo sin errores antes de confirmar la importación.';

            return;
        }

        $empresa = $this->resolveSelectedEmpresa();
        $this->importError = null;

        $fileInfo = is_array($this->previewResult['file_info'] ?? null)
            ? $this->previewResult['file_info']
            : [];

        try {
            $result = $service->importStoredFile($empresa, auth()->user(), $this->stagedFilePath, [
                'tone' => $this->tone,
                'filename' => $fileInfo['filename'] ?? null,
                'import_name' => $fileInfo['import_name'] ?? null,
            ]);

            $this->previewResult = $this->prepareResultForView($result);
            $this->canConfirmImport = false;
            $this->xlsxFile = null;

            if (($result['persisted'] ?? false) === true) {
                session()->flash('content_import_saved', 'Importación XLSX completada correctamente.');
            } else {
                $this->importError = 'La importación no pudo completarse.';
            }
        } catch (Throwable $e) {
            Log::error('[CONTENT][XLSX][CONFIRM] Error durante importación definitiva.', [
                'empresa_id' => $empresa->id,
                'user_id' => auth()->id(),
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            $this->importError = 'Error interno al confirmar la importación XLSX.';
        } finally {
            $service->deleteTemporaryFile($this->stagedFilePath);
            $this->stagedFilePath = null;
        }
    }

    public function cancelImport(): void
    {
        /** @var ContentXlsxImportService $service */
        $service = app(ContentXlsxImportService::class);
        $this->resetImportState($service, true);
        $this->xlsxFile = null;
    }

    public function render()
    {
        return view('livewire.admin.content-management.content-import-manager', [
            'hasMultipleEmpresas' => count($this->authorizedEmpresas) > 1,
        ]);
    }

    private function rules(): array
    {
        return array_merge([
            'selectedEmpresaId' => ['required', 'integer'],
            'tone' => ['required', 'in:' . implode(',', ContentArticle::TONES)],
        ], $this->fileRules());
    }

    private function fileRules(): array
    {
        return [
            'xlsxFile' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    private function messages(): array
    {
        return [
            'selectedEmpresaId.required' => 'Debes seleccionar una empresa.',
            'selectedEmpresaId.integer' => 'La empresa seleccionada no es válida.',
            'tone.required' => 'Debes seleccionar el tono del artículo.',
            'tone.in' => 'El tono debe ser tuteo o usteo.',
            'xlsxFile.required' => 'Debes seleccionar un archivo XLSX.',
            'xlsxFile.file' => 'El archivo cargado no es válido.',
            'xlsxFile.mimes' => 'Solo se aceptan archivos .xlsx.',
            'xlsxFile.max' => 'El archivo no puede superar 10 MB.',
        ];
    }

    private function resolveSelectedEmpresa(): Empresa
    {
        $empresaId = (int) $this->selectedEmpresaId;
        $authorizedIds = collect($this->authorizedEmpresas)
            ->pluck('id')
            ->map(function ($id): int {
                return (int) $id;
            })
            ->all();

        if (! in_array($empresaId, $authorizedIds, true)) {
            throw ValidationException::withMessages([
                'selectedEmpresaId' => 'La empresa seleccionada no está autorizada para este usuario.',
            ]);
        }

        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa instanceof Empresa) {
            throw ValidationException::withMessages([
                'selectedEmpresaId' => 'La empresa seleccionada no existe.',
            ]);
        }

        return $empresa;
    }

    /**
     * @return Collection<int, Empresa>
     */
    private function resolveAuthorizedEmpresas(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Empresa::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        if ((int) $user->empresa_id > 0) {
            return Empresa::query()
                ->where('id', (int) $user->empresa_id)
                ->get(['id', 'nombre']);
        }

        return collect();
    }

    private function resetImportState(ContentXlsxImportService $service, bool $deleteTemporaryFile): void
    {
        if ($deleteTemporaryFile) {
            $service->deleteTemporaryFile($this->stagedFilePath);
        }

        $this->stagedFilePath = null;
        $this->previewResult = null;
        $this->importError = null;
        $this->canConfirmImport = false;
        $this->resetErrorBag();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function prepareResultForView(array $result): array
    {
        $errors = isset($result['errors']) && is_array($result['errors'])
            ? $result['errors']
            : [];

        $result['errors_preview'] = array_slice($errors, 0, 25);
        $result['errors_remaining'] = max(count($errors) - count($result['errors_preview']), 0);

        return $result;
    }
}
