<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentImport extends Model
{
    protected $table = 'content_imports';

    protected $fillable = [
        'empresa_id',
        'import_name',
        'source_file_name',
        'imported_by',
        'imported_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'imported_by' => 'integer',
        'imported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function importedBy()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function articles()
    {
        return $this->hasMany(ContentArticle::class, 'content_import_id');
    }
}
