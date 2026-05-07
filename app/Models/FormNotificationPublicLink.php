<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormNotificationPublicLink extends Model
{
    protected $table = 'form_notification_public_links';

    protected $fillable = [
        'whatsapp_form_notification_id',
        'token_hash',
        'token_encrypted',
        'is_active',
        'expires_at',
        'revoked_at',
        'first_accessed_at',
        'last_accessed_at',
        'access_count',
    ];

    protected $hidden = [
        'token_hash',
        'token_encrypted',
    ];

    protected $casts = [
        'whatsapp_form_notification_id' => 'integer',
        'is_active' => 'boolean',
        'access_count' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_accessed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function whatsappFormNotification()
    {
        return $this->belongsTo(WhatsappFormNotification::class, 'whatsapp_form_notification_id');
    }
}
