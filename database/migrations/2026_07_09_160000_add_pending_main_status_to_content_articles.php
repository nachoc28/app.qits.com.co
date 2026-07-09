<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\ContentManagement\ContentArticleInitialStatusBackfillService;

class AddPendingMainStatusToContentArticles extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE content_articles MODIFY main_status ENUM('pending','processing','unpublished','published') NOT NULL DEFAULT 'pending'");
        }

        (new ContentArticleInitialStatusBackfillService())->backfillUnstartedProcessingArticles();
    }

    public function down(): void
    {
        DB::table('content_articles')
            ->where('main_status', 'pending')
            ->update(['main_status' => 'processing']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE content_articles MODIFY main_status ENUM('processing','unpublished','published') NOT NULL DEFAULT 'processing'");
        }
    }
}
