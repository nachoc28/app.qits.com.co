<?php

namespace App\Services\ContentManagement;

use Illuminate\Support\Facades\DB;

class ContentArticleInitialStatusBackfillService
{
    public function backfillUnstartedProcessingArticles(): int
    {
        return DB::table('content_articles')
            ->where('main_status', 'processing')
            ->where('operational_stage', 'pending')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('content_article_generations')
                    ->whereColumn('content_article_generations.content_article_id', 'content_articles.id');
            })
            ->update(['main_status' => 'pending']);
    }
}
