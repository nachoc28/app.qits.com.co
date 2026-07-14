<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiFlow extends Model
{
    protected $table = 'ai_flows';

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function versions()
    {
        return $this->hasMany(AiFlowVersion::class, 'ai_flow_id');
    }

    public function publishedVersions()
    {
        return $this->hasMany(AiFlowVersion::class, 'ai_flow_id')
            ->where('status', AiFlowVersion::STATUS_PUBLISHED);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions()
    {
        return $this->hasMany(AiFlowExecution::class, 'ai_flow_id');
    }
}
