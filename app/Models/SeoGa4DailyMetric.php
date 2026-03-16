<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoGa4DailyMetric extends Model
{
    protected $table = 'seo_ga4_daily_metrics';

    protected $fillable = [
        'empresa_id',
        'metric_date',
        'users',
        'sessions',
        'engaged_sessions',
        'conversions',
        'organic_sessions',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'metric_date' => 'date',
        'users' => 'integer',
        'sessions' => 'integer',
        'engaged_sessions' => 'integer',
        'conversions' => 'integer',
        'organic_sessions' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
