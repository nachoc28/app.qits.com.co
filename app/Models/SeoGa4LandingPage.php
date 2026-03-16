<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoGa4LandingPage extends Model
{
    protected $table = 'seo_ga4_landing_pages';

    protected $fillable = [
        'empresa_id',
        'metric_date',
        'landing_page',
        'users',
        'sessions',
        'conversions',
        'engagement_rate',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'metric_date' => 'date',
        'users' => 'integer',
        'sessions' => 'integer',
        'conversions' => 'integer',
        'engagement_rate' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
