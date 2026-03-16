<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoGscPage extends Model
{
    protected $table = 'seo_gsc_pages';

    protected $fillable = [
        'empresa_id',
        'metric_date',
        'page_url',
        'clicks',
        'impressions',
        'ctr',
        'avg_position',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'metric_date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:4',
        'avg_position' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
