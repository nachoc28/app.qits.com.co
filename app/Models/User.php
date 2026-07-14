<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name','email','password','telefono','empresa_id','tipo_usuario_id','active'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'empresa_id'        => 'integer',
        'tipo_usuario_id'   => 'integer',
        'active'            => 'boolean',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function isAdmin(): bool
    {
        return optional($this->tipoUsuario)->nombre === 'Administrador';
    }

    /** Relaciones propias */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoUsuario()
    {
        return $this->belongsTo(TipoUsuario::class, 'tipo_usuario_id');
    }

    public function ticketsAsignados()
    {
        return $this->hasMany(Ticket::class, 'responsable_id');
    }

    public function leadsConvertidos()
    {
        return $this->hasMany(Lead::class, 'converted_user_id');
    }

    public function contentImports()
    {
        return $this->hasMany(ContentImport::class, 'imported_by');
    }

    public function contentReadySteps()
    {
        return $this->hasMany(ContentArticleStep::class, 'ready_by');
    }

    public function contentGenerations()
    {
        return $this->hasMany(ContentArticleGeneration::class, 'generated_by');
    }

    public function contentFiles()
    {
        return $this->hasMany(ContentArticleFile::class, 'uploaded_by');
    }

    public function deliveredContentArticles()
    {
        return $this->hasMany(ContentArticle::class, 'delivered_by');
    }

    public function publishedContentArticles()
    {
        return $this->hasMany(ContentArticle::class, 'published_by');
    }

    public function createdAiFlows()
    {
        return $this->hasMany(AiFlow::class, 'created_by');
    }

    public function publishedAiFlowVersions()
    {
        return $this->hasMany(AiFlowVersion::class, 'published_by');
    }

    public function startedAiFlowExecutions()
    {
        return $this->hasMany(AiFlowExecution::class, 'started_by');
    }

    public function startedAiFlowExecutionSteps()
    {
        return $this->hasMany(AiFlowExecutionStep::class, 'started_by');
    }

    public function completedAiFlowExecutionSteps()
    {
        return $this->hasMany(AiFlowExecutionStep::class, 'completed_by');
    }

    public function filledAiFlowExecutionValues()
    {
        return $this->hasMany(AiFlowExecutionValue::class, 'filled_by');
    }

    public function generatedAiFlowStepGenerations()
    {
        return $this->hasMany(AiFlowStepGeneration::class, 'generated_by');
    }

    public function savedAiFlowStepResults()
    {
        return $this->hasMany(AiFlowStepResult::class, 'saved_by');
    }

    public function markedAiFlowStrategicOutputs()
    {
        return $this->hasMany(AiFlowStrategicOutput::class, 'marked_by');
    }

}
