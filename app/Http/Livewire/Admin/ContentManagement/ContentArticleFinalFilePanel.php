<?php

namespace App\Http\Livewire\Admin\ContentManagement;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;
use App\Models\User;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentFinalFileService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContentArticleFinalFilePanel extends Component
{
    use WithFileUploads;

    /** @var int */
    public $articleId;

    /** @var mixed */
    public $uploadFile;

    public function mount(int $articleId, ContentAccessService $accessService): void
    {
        if (! auth()->check()) {
            abort(401);
        }

        $this->articleId = $articleId;
        $this->resolveArticle($accessService);
    }

    public function updatedUploadFile(): void
    {
        $this->validateOnly('uploadFile', $this->uploadRules(), $this->uploadMessages());
    }

    public function uploadFinalFile(
        ContentAccessService $accessService,
        ContentFinalFileService $fileService
    ): void {
        $this->validate($this->uploadRules(), $this->uploadMessages());
        $article = $this->resolveArticle($accessService);

        /** @var User $user */
        $user = auth()->user();

        try {
            $fileService->upload($article, $this->uploadFile, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'uploadFile' => $e->getMessage(),
            ]);
        }

        $this->uploadFile = null;
        session()->flash('content_final_file_success', 'Nueva version de archivo final cargada correctamente.');
    }

    public function render(
        ContentAccessService $accessService,
        ContentFinalFileService $fileService
    ) {
        $article = $this->resolveArticle($accessService);
        $videoStep = $article->steps->firstWhere('step_type', ContentArticleStep::TYPE_VIDEO_INSTAGRAM);
        $files = $article->files->sortByDesc('version_number')->values();

        return view('livewire.admin.content-management.content-article-final-file-panel', [
            'article' => $article,
            'videoStep' => $videoStep,
            'files' => $files,
            'availability' => $fileService->availability($article),
        ]);
    }

    private function uploadRules(): array
    {
        return [
            'uploadFile' => [
                'required',
                'file',
                'max:' . (int) config('content_management.final_files.max_file_kb', 10240),
            ],
        ];
    }

    private function uploadMessages(): array
    {
        return [
            'uploadFile.required' => 'Debes seleccionar un archivo final.',
            'uploadFile.file' => 'El archivo cargado no es valido.',
            'uploadFile.max' => 'El archivo final supera el tamaño maximo permitido.',
        ];
    }

    private function resolveArticle(ContentAccessService $accessService): ContentArticle
    {
        /** @var User $user */
        $user = auth()->user();

        $article = ContentArticle::query()
            ->with([
                'contentImport.empresa',
                'steps.readyBy',
                'files.uploadedBy',
            ])
            ->findOrFail((int) $this->articleId);

        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $article;
    }
}
