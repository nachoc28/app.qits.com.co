<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentArticleFile extends Model
{
    protected $table = 'content_article_files';

    protected $fillable = [
        'content_article_id',
        'version_number',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'content_article_id' => 'integer',
        'version_number' => 'integer',
        'file_size' => 'integer',
        'uploaded_by' => 'integer',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(ContentArticle::class, 'content_article_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
