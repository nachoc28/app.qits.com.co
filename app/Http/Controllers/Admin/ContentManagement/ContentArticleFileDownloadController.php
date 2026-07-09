<?php

namespace App\Http\Controllers\Admin\ContentManagement;

use App\Http\Controllers\Controller;
use App\Models\ContentArticle;
use App\Models\ContentArticleFile;
use App\Services\ContentManagement\ContentAccessService;
use App\Services\ContentManagement\ContentFinalFileService;

class ContentArticleFileDownloadController extends Controller
{
    public function __invoke(
        ContentArticle $article,
        ContentArticleFile $file,
        ContentAccessService $accessService,
        ContentFinalFileService $fileService
    ) {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $article->loadMissing('contentImport');

        abort_unless($file->content_article_id === $article->id, 404);
        abort_unless($accessService->canAccessArticle($user, $article), 403);

        return $fileService->downloadResponse($file);
    }
}
