<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDocument extends Model
{
    public const TYPE_PDF = 'pdf';
    public const TYPE_IMAGE = 'image';
    public const TYPE_ATTACHMENT = 'attachment';

    protected $table = 'lead_documents';

    /**
     * Valores de referencia para `document_type`:
     *   pdf | image | attachment
     */
    protected $fillable = [
        'lead_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'document_type',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function lead()
    {
        return $this->belongsTo(WaLead::class, 'lead_id');
    }
}
